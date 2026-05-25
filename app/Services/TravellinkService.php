<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TravellinkService
{
    private const PROVIDER = 'travellink';

    public function searchEnabled(): bool
    {
        return (bool) Setting::get('travellink_search_enabled', false);
    }

    public function emissionEnabled(): bool
    {
        return (bool) Setting::get('travellink_emission_enabled', false);
    }

    public function autoEmissionEnabled(): bool
    {
        return $this->emissionEnabled()
            && (bool) Setting::get('travellink_auto_emission_enabled', false);
    }

    public function dryRunEnabled(): bool
    {
        return (bool) Setting::get('travellink_dry_run', true);
    }

    public function configured(): bool
    {
        return $this->isConfigured();
    }

    public function canIssueOrder(Order $order): bool
    {
        if (! $this->emissionEnabled()) {
            return false;
        }

        $order->loadMissing('flights');

        if ($order->flights->isEmpty()) {
            return false;
        }

        return $order->flights->every(function ($flight): bool {
            $payload = is_array($flight->provider_payload) ? $flight->provider_payload : [];

            return $flight->source_provider === self::PROVIDER
                && ! empty($payload['viagem_id'])
                && ! empty($payload['identificacao_da_viagem']);
        });
    }

    public function canAutoIssueOrder(Order $order): bool
    {
        return $this->autoEmissionEnabled() && $this->canIssueOrder($order);
    }

    public function issueOrder(Order $order): array
    {
        if (! $this->canIssueOrder($order)) {
            throw new \RuntimeException('Pedido não elegível para emissão Travellink.');
        }

        $order->loadMissing(['flights', 'passengers']);

        if ($order->passengers->isEmpty()) {
            throw new \RuntimeException('Pedido sem passageiros para emissão Travellink.');
        }

        $tarifarPayload = $this->tarifarPayload($order);
        $reservarPayload = $this->reservarPayload($order);

        if ($this->dryRunEnabled()) {
            return [
                'dry_run' => true,
                'localizador' => null,
                'tickets' => [],
                'steps' => ['Tarifar', 'Reservar', 'IniciarEmissao', 'Emitir'],
                'payload_summary' => [
                    'classes' => count($tarifarPayload['ClassesSelecionadas'] ?? []),
                    'passengers' => count($reservarPayload['Passageiros'] ?? []),
                    'has_outbound' => ! empty($tarifarPayload['IdentificacaoDaViagem']),
                    'has_inbound' => ! empty($tarifarPayload['IdentificacaoDaViagemVolta']),
                ],
            ];
        }

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Credenciais Travellink não configuradas.');
        }

        $tarifarResponse = $this->request('Tarifar', $tarifarPayload);
        $reservarResponse = $this->request('Reservar', $reservarPayload);
        $localizador = $this->extractLocalizador($reservarResponse);

        if (! $localizador) {
            throw new \RuntimeException('Travellink não retornou localizador na reserva.');
        }

        $iniciarEmissaoResponse = $this->request('IniciarEmissao', [
            'Localizador' => $localizador,
        ]);

        $paymentPayload = $this->paymentPayload();
        if (empty($paymentPayload)) {
            throw new \RuntimeException('Pagamento da emissão Travellink não configurado.');
        }

        $emitirResponse = $this->request('Emitir', [
            'Localizador' => $localizador,
            'Pagamento' => $paymentPayload,
        ]);

        return [
            'dry_run' => false,
            'localizador' => $localizador,
            'tickets' => $this->extractTickets($emitirResponse),
            'responses' => [
                'tarifar_total' => $tarifarResponse['Preco']['TotalGeral'] ?? $tarifarResponse['Preco']['Total'] ?? null,
                'iniciar_emissao' => [
                    'foid_obrigatorio' => (bool) ($iniciarEmissaoResponse['ConfiguracoesDeEmissao']['FoidObrigatorio'] ?? false),
                    'exige_chave' => (bool) ($iniciarEmissaoResponse['ConfiguracoesDeEmissao']['ExigirChaveDeSeguranca'] ?? false),
                ],
            ],
        ];
    }

    public function tarifarPayload(Order $order): array
    {
        $payload = $this->baseTripPayload($order);
        $payload['RetornarPlanoDeFinanciamento'] = true;
        $payload['RetornarRegrasTarifarias'] = true;
        $payload['TarifarMelhorFamilia'] = true;
        $payload['TarifarMelhorPreco'] = true;

        return $payload;
    }

    public function reservarPayload(Order $order): array
    {
        $order->loadMissing(['passengers', 'flights']);

        return array_merge($this->baseTripPayload($order), [
            'Contatos' => $this->contactPayloads($order),
            'HabilitarFluxoContatoPassageiro' => true,
            'Passageiros' => $order->passengers
                ->values()
                ->map(fn ($passenger) => $this->passengerPayload($passenger))
                ->all(),
            'RetornarDadosSessao' => true,
            'TarifarMelhorFamilia' => true,
            'TarifarMelhorPreco' => true,
        ]);
    }

    /**
     * Busca voos na Travellink e retorna o formato flat usado pelo checkout.
     *
     * @return array{outbound: array, inbound: array}
     */
    public function searchFlights(array $params, ?array $airlines = null): array
    {
        if (! $this->searchEnabled() || ! $this->isConfigured()) {
            return ['outbound' => [], 'inbound' => []];
        }

        try {
            $payload = $this->availabilityPayload($params, $airlines);
            $response = $this->request('Disponibilidade', $payload);

            return $this->transformAvailabilityResponse($response, $params);
        } catch (\Throwable $e) {
            Log::warning('Travellink: falha na busca', [
                'error' => $e->getMessage(),
                'params' => $this->safeLogParams($params),
            ]);

            return ['outbound' => [], 'inbound' => []];
        }
    }

    public function recuperarSistemasPesquisa(array $params): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return $this->request('RecuperarSistemasPesquisa', [
            'Origem' => strtoupper((string) ($params['departure'] ?? $params['origin'] ?? '')),
            'Destino' => strtoupper((string) ($params['arrival'] ?? $params['destination'] ?? '')),
            'Timeout' => (int) Setting::get('travellink_timeout', config('services.travellink.timeout', 45)),
        ]);
    }

    public function availabilityPayload(array $params, ?array $airlines = null): array
    {
        $payload = [
            'ClienteId' => (int) Setting::get('travellink_client_id', 0),
            'ApenasVoosComBagagem' => false,
            'ApenasVoosDiretos' => false,
            'BuscarVoosComBagagem' => true,
            'BuscarVoosSemBagagem' => true,
            'Cabine' => $this->mapCabin($params['cabin'] ?? 'EC'),
            'CompanhiasPreferenciais' => $this->airlineCodes($airlines),
            'DataIda' => $this->formatMicrosoftDate((string) ($params['outbound_date'] ?? '')),
            'Destino' => strtoupper((string) ($params['arrival'] ?? '')),
            'Flex' => false,
            'Origem' => strtoupper((string) ($params['departure'] ?? '')),
            'MultiplosTrechos' => [],
            'QuantidadeAdultos' => (int) ($params['adults'] ?? 1),
            'QuantidadeBebes' => (int) ($params['infants'] ?? 0),
            'QuantidadeCriancas' => (int) ($params['children'] ?? 0),
            'QuantidadeDeVoos' => (int) Setting::get('travellink_max_flights', 50),
            'Recomendacao' => false,
            'Sistema' => (int) Setting::get('travellink_system', 0),
        ];

        if (! empty($params['inbound_date'])) {
            $payload['DataVolta'] = $this->formatMicrosoftDate((string) $params['inbound_date']);
        }

        return $payload;
    }

    public function request(string $service, array $payload): array
    {
        $url = $this->endpointUrl($service);
        $payload = array_merge($this->credentialsPayload(), $payload);

        $response = Http::withHeaders($this->headers())
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->post($url, $payload);

        if ($response->failed()) {
            throw new \RuntimeException("Travellink retornou status {$response->status()}");
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new \RuntimeException('Travellink retornou uma resposta inválida.');
        }

        if (! empty($data['Exception'])) {
            $message = is_array($data['Exception'])
                ? json_encode($data['Exception'], JSON_UNESCAPED_UNICODE)
                : (string) $data['Exception'];

            throw new \RuntimeException("Travellink retornou erro: {$message}");
        }

        return $data;
    }

    /**
     * @return array{outbound: array, inbound: array}
     */
    public function transformAvailabilityResponse(array $data, array $params = []): array
    {
        return [
            'outbound' => $this->transformTrips($data['ViagensTrecho1'] ?? [], 'outbound', $params),
            'inbound' => $this->transformTrips($data['ViagensTrecho2'] ?? [], 'inbound', $params),
        ];
    }

    private function transformTrips(array $trips, string $direction, array $params): array
    {
        $flights = [];

        foreach ($trips as $trip) {
            if (! is_array($trip)) {
                continue;
            }

            $segments = array_values(array_filter($trip['Voos'] ?? [], 'is_array'));
            if (empty($segments)) {
                continue;
            }

            $first = $segments[0];
            $last = $segments[array_key_last($segments)];
            $price = is_array($trip['Preco'] ?? null) ? $trip['Preco'] : [];
            $airlineCode = $this->segmentAirlineCode($first) ?: $this->airlineCodeFromTrip($trip);
            $operator = $this->operatorFromCode($airlineCode);
            $flightNumber = $this->formatFlightNumber($first, $airlineCode);
            $boardingTax = $this->money($price['Taxa'] ?? $price['TotalTaxaEmbarque'] ?? 0);
            $airlineBoardingTax = $this->money($price['TotalTaxaEmbarque'] ?? 0);
            $connection = $this->connectionSegments($segments);

            $flights[] = [
                'operator' => $operator,
                'flight_number' => $flightNumber,
                'departure_time' => $this->segmentTime($first, 'departure'),
                'arrival_time' => $this->segmentTime($last, 'arrival'),
                'departure_location' => $this->iata($first['Origem'] ?? []),
                'arrival_location' => $this->iata($last['Destino'] ?? []),
                'departure_label' => $this->locationLabel($first['Origem'] ?? []),
                'arrival_label' => $this->locationLabel($last['Destino'] ?? []),
                'boarding_tax' => $boardingTax,
                'airline_boarding_tax' => $airlineBoardingTax,
                'class_service' => $this->classService($first, $params['cabin'] ?? 'EC'),
                'price_money' => $this->money($price['TotalTarifa'] ?? $price['Tarifa'] ?? 0),
                'price_miles' => '0',
                'price_miles_vip' => null,
                'total_flight_duration' => $this->duration($trip['TempoDeDuracao'] ?? $trip['Duracao'] ?? null),
                'unique_id' => $this->uniqueId($trip, $direction, $segments),
                'connection' => $connection,
                'baggage' => $this->baggage($segments),
                'provider_payload' => $this->providerPayload($trip, $segments),
            ];
        }

        return $flights;
    }

    private function providerPayload(array $trip, array $segments): array
    {
        return [
            'viagem_id' => $trip['Id'] ?? null,
            'identificacao_da_viagem' => $trip['IdentificacaoDaViagem'] ?? null,
            'sistema' => (int) Setting::get('travellink_system', 0),
            'fornecedor_codigo' => $trip['Fornecedor']['Codigo'] ?? null,
            'fornecedor_nome' => $trip['Fornecedor']['Nome'] ?? null,
            'classes_selecionadas' => array_values(array_map(function (array $segment): array {
                return [
                    'BaseTarifaria' => $segment['BaseTarifaria'] ?? '',
                    'Classe' => $segment['Classe'] ?? '',
                    'Familia' => $segment['FamiliaCodigo'] ?? $segment['Familia'] ?? '',
                    'NumeroDoVoo' => (string) ($segment['Numero'] ?? $this->formatFlightNumber($segment, $this->segmentAirlineCode($segment))),
                ];
            }, $segments)),
        ];
    }

    private function connectionSegments(array $segments): array
    {
        return array_values(array_map(function (array $segment): array {
            $airlineCode = $this->segmentAirlineCode($segment);

            return [
                'DEPARTURE_TIME' => $this->segmentTime($segment, 'departure'),
                'ARRIVAL_TIME' => $this->segmentTime($segment, 'arrival'),
                'DEPARTURE_LOCATION' => $this->iata($segment['Origem'] ?? []),
                'ARRIVAL_LOCATION' => $this->iata($segment['Destino'] ?? []),
                'FLIGHT_NUMBER' => $this->formatFlightNumber($segment, $airlineCode),
                'FLIGHT_DURATION' => $this->duration($segment['Duracao'] ?? null),
                'OP' => $this->operatorFromCode($airlineCode),
                'TIME_WAITING' => $segment['TempoEspera'] ?? $segment['TempoDeEspera'] ?? null,
            ];
        }, $segments));
    }

    private function baggage(array $segments): array
    {
        $checkedQuantity = 0;
        $checkedWeight = null;

        foreach ($segments as $segment) {
            $quantity = (int) ($segment['BagagemQuantidade'] ?? 0);
            $checkedQuantity = max($checkedQuantity, $quantity);

            if ($checkedWeight === null && ! empty($segment['BagagemPeso'])) {
                $unit = $segment['BagagemUnidadeDeMedida'] ?? 'kg';
                $checkedWeight = trim((string) $segment['BagagemPeso'].' '.$unit);
            }
        }

        return [
            'fare' => isset($segments[0]['Familia']) ? (string) $segments[0]['Familia'] : null,
            'personal_item' => [
                'included' => true,
                'quantity' => 1,
                'weight' => null,
            ],
            'carry_on' => [
                'included' => true,
                'quantity' => 1,
                'weight' => null,
            ],
            'checked' => [
                'included' => $checkedQuantity > 0,
                'quantity' => $checkedQuantity,
                'weight' => $checkedWeight,
            ],
        ];
    }

    private function airlineCodes(?array $airlines): array
    {
        $codes = [];

        foreach ($airlines ?? [] as $airline) {
            $normalized = strtoupper(trim((string) $airline));
            if ($normalized === '' || $normalized === 'ALL') {
                continue;
            }

            $codes = array_merge($codes, match ($normalized) {
                'GOL', 'G3' => ['G3'],
                'AZUL', 'AD' => ['AD'],
                'LATAM', 'LA', 'JJ' => ['LA', 'JJ'],
                default => [$normalized],
            });
        }

        return array_values(array_unique($codes));
    }

    private function classService(array $segment, string $cabin): string
    {
        if (! empty($segment['Cabine'])) {
            return (string) $segment['Cabine'];
        }

        return strtoupper($cabin) === 'EX' ? 'Executiva' : 'Econômica';
    }

    private function segmentAirlineCode(array $segment): string
    {
        foreach (['CiaOperacional', 'CiaMandatoria', 'Companhia'] as $key) {
            $value = $segment[$key] ?? null;
            if (is_array($value) && ! empty($value['CodigoIata'])) {
                return strtoupper((string) $value['CodigoIata']);
            }
            if (is_string($value) && $value !== '') {
                return strtoupper($value);
            }
        }

        return '';
    }

    private function airlineCodeFromTrip(array $trip): string
    {
        $cia = $trip['CiaMandatoria'] ?? null;

        return is_array($cia) ? strtoupper((string) ($cia['CodigoIata'] ?? '')) : '';
    }

    private function operatorFromCode(string $code): string
    {
        return match (strtoupper($code)) {
            'G3' => 'GOL',
            'AD' => 'AZUL',
            'LA', 'JJ' => 'LATAM',
            default => strtoupper($code),
        };
    }

    private function formatFlightNumber(array $segment, string $airlineCode): string
    {
        $number = strtoupper(trim((string) ($segment['Numero'] ?? '')));
        if ($number === '') {
            return strtoupper($airlineCode);
        }

        if (preg_match('/^[A-Z]{2}\d+/', $number)) {
            return $number;
        }

        return strtoupper($airlineCode).$number;
    }

    private function segmentTime(array $segment, string $type): string
    {
        $field = $type === 'departure' ? 'HoraSaida' : 'HoraChegada';
        $dateField = $type === 'departure' ? 'DataSaida' : 'DataChegada';
        $raw = preg_replace('/\D/', '', (string) ($segment[$field] ?? '')) ?? '';

        if (strlen($raw) >= 3) {
            $raw = str_pad($raw, 4, '0', STR_PAD_LEFT);

            return substr($raw, 0, 2).':'.substr($raw, 2, 2);
        }

        $date = $this->parseMicrosoftDate($segment[$dateField] ?? null);

        return $date?->format('H:i') ?? '';
    }

    private function iata(mixed $location): ?string
    {
        return is_array($location) && ! empty($location['CodigoIata'])
            ? strtoupper((string) $location['CodigoIata'])
            : null;
    }

    private function locationLabel(mixed $location): ?string
    {
        if (! is_array($location)) {
            return null;
        }

        $iata = $this->iata($location);
        $description = trim((string) ($location['Descricao'] ?? ''));

        if ($description === '') {
            return $iata;
        }

        return $iata && ! str_contains($description, "({$iata})")
            ? "{$description} ({$iata})"
            : $description;
    }

    private function duration(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $minutes = (int) $value;

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        $value = trim((string) $value);
        if (preg_match('/^\d{1,2}:\d{2}$/', $value)) {
            [$hours, $minutes] = explode(':', $value);

            return sprintf('%02d:%02d', (int) $hours, (int) $minutes);
        }

        return $value;
    }

    private function uniqueId(array $trip, string $direction, array $segments): string
    {
        return 'tl_'.sha1(json_encode([
            'direction' => $direction,
            'id' => $trip['Id'] ?? null,
            'identificacao' => $trip['IdentificacaoDaViagem'] ?? null,
            'segments' => array_map(fn (array $segment): array => [
                $this->formatFlightNumber($segment, $this->segmentAirlineCode($segment)),
                $segment['DataSaida'] ?? null,
                $segment['Origem']['CodigoIata'] ?? null,
                $segment['Destino']['CodigoIata'] ?? null,
            ], $segments),
        ]));
    }

    private function mapCabin(string $cabin): string
    {
        return strtoupper($cabin) === 'EX' ? 'C' : 'Y';
    }

    private function formatMicrosoftDate(string $date): string
    {
        $carbon = Carbon::parse($date, config('app.timezone', 'America/Sao_Paulo'))->startOfDay();
        $milliseconds = $carbon->getTimestamp() * 1000;

        return "/Date({$milliseconds}{$carbon->format('O')})/";
    }

    private function formatMicrosoftDateFromCarbon(?Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        $date = $date->copy()->startOfDay();
        $milliseconds = $date->getTimestamp() * 1000;

        return "/Date({$milliseconds}{$date->format('O')})/";
    }

    private function parseMicrosoftDate(mixed $date): ?Carbon
    {
        if (! is_string($date) || ! preg_match('/\/Date\((-?\d+)([+-]\d{4})?\)\//', $date, $matches)) {
            return null;
        }

        return Carbon::createFromTimestamp(intdiv((int) $matches[1], 1000), config('app.timezone', 'America/Sao_Paulo'));
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return number_format($this->toFloat($value), 2, ',', '');
    }

    private function toFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = preg_replace('/[^\d,.\-]/', '', (string) $value) ?? '';
        if ($value === '' || $value === '-' || $value === ',' || $value === '.') {
            return 0.0;
        }

        if (str_contains($value, ',')) {
            return (float) str_replace(',', '.', str_replace('.', '', $value));
        }

        return (float) $value;
    }

    private function isConfigured(): bool
    {
        return $this->baseUrl() !== ''
            && $this->login() !== ''
            && $this->password() !== ''
            && $this->developerToken() !== ''
            && $this->developerAccessCode() !== '';
    }

    private function endpointUrl(string $service): string
    {
        return rtrim($this->baseUrl(), '/').'/'.Str::of($service)->trim('/');
    }

    private function headers(): array
    {
        return [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Developer-Token' => $this->developerToken(),
            'Developer-Access-Code' => $this->developerAccessCode(),
        ];
    }

    private function credentialsPayload(): array
    {
        return [
            'Login' => $this->login(),
            'Senha' => $this->password(),
        ];
    }

    private function baseUrl(): string
    {
        return trim((string) Setting::get('travellink_base_url', config('services.travellink.base_url', '')));
    }

    private function login(): string
    {
        return trim((string) Setting::get('travellink_login', config('services.travellink.login', '')));
    }

    private function password(): string
    {
        return (string) Setting::get('travellink_password', config('services.travellink.password', ''));
    }

    private function developerToken(): string
    {
        return trim((string) Setting::get('travellink_developer_token', config('services.travellink.developer_token', '')));
    }

    private function developerAccessCode(): string
    {
        return trim((string) Setting::get('travellink_developer_access_code', config('services.travellink.developer_access_code', '')));
    }

    private function timeout(): int
    {
        return (int) Setting::get('travellink_timeout', config('services.travellink.timeout', 45));
    }

    private function safeLogParams(array $params): array
    {
        return array_intersect_key($params, array_flip([
            'departure', 'arrival', 'outbound_date', 'inbound_date',
            'adults', 'children', 'infants', 'cabin',
        ]));
    }

    private function baseTripPayload(Order $order): array
    {
        $order->loadMissing('flights');

        $outbound = $order->flights->firstWhere('direction', 'outbound');
        $inbound = $order->flights->firstWhere('direction', 'inbound');
        $outboundPayload = is_array($outbound?->provider_payload) ? $outbound->provider_payload : [];
        $inboundPayload = is_array($inbound?->provider_payload) ? $inbound->provider_payload : [];

        $payload = [
            'ClienteId' => (int) Setting::get('travellink_client_id', 0),
            'ClassesSelecionadas' => array_values(array_merge(
                $outboundPayload['classes_selecionadas'] ?? [],
                $inboundPayload['classes_selecionadas'] ?? [],
            )),
            'IdentificacaoDaViagem' => $outboundPayload['identificacao_da_viagem'] ?? '',
            'ViagemIda' => (int) ($outboundPayload['viagem_id'] ?? 0),
        ];

        if (! empty($inboundPayload)) {
            $payload['IdentificacaoDaViagemVolta'] = $inboundPayload['identificacao_da_viagem'] ?? '';
            $payload['ViagemVolta'] = (int) ($inboundPayload['viagem_id'] ?? 0);
        }

        return $payload;
    }

    private function passengerPayload($passenger): array
    {
        [$firstName, $middleName, $lastName] = $this->splitName((string) $passenger->full_name);
        $document = preg_replace('/\D/', '', (string) $passenger->document) ?: null;
        $nationality = strtoupper((string) ($passenger->nationality ?: 'BR'));
        $passportNumber = $passenger->passport_number ? strtoupper((string) $passenger->passport_number) : null;
        $phone = $this->splitPhone((string) $passenger->phone);

        $documentPayload = $passportNumber
            ? [
                'Nacionalidade' => $nationality,
                'Numero' => $passportNumber,
                'PaisEmissor' => $nationality,
                'Tipo' => 4,
                'Validade' => $this->formatMicrosoftDateFromCarbon($passenger->passport_expiry),
            ]
            : [
                'Nacionalidade' => $nationality,
                'Numero' => $document,
                'PaisEmissor' => $nationality,
                'Tipo' => 1,
            ];

        $payload = [
            'Id' => 0,
            'CPF' => $document,
            'Documento' => $this->removeNulls($documentPayload),
            'Email' => $passenger->email,
            'FaixaEtaria' => $this->ageRange($passenger->birth_date),
            'Nascimento' => $this->formatMicrosoftDateFromCarbon($passenger->birth_date),
            'Nome' => $firstName,
            'NomeDoMeio' => $middleName,
            'RecusarEnvioDoEmailParaFornecedor' => false,
            'RecusarEnvioDoTelefoneParaFornecedor' => false,
            'Sobrenome' => $lastName,
            'Telefone' => [
                'Id' => 0,
                'Email' => $passenger->email,
                'NumeroDDD' => $phone['ddd'],
                'NumeroDDI' => $phone['ddi'],
                'NumeroTelefone' => $phone['number'],
                'Nome' => $firstName,
                'Tipo' => 1,
            ],
        ];

        if ($passportNumber) {
            $payload['Passaporte'] = $this->removeNulls([
                'Id' => 0,
                'Nacionalidade' => $nationality,
                'NomeDoMeioPax' => $middleName,
                'NomePax' => $firstName,
                'Numero' => $passportNumber,
                'PaisEmissor' => $nationality,
                'SobrenomePax' => $lastName,
                'Validade' => $this->formatMicrosoftDateFromCarbon($passenger->passport_expiry),
            ]);
        }

        return $this->removeNulls($payload);
    }

    private function contactPayloads(Order $order): array
    {
        $passenger = $order->passengers->first();
        $phone = $this->splitPhone((string) ($passenger?->phone ?? ''));

        return [
            [
                'Id' => 0,
                'Email' => $passenger?->email,
                'NumeroDDD' => $phone['ddd'],
                'NumeroDDI' => $phone['ddi'],
                'NumeroTelefone' => $phone['number'],
                'Nome' => $passenger?->full_name,
                'Tipo' => 1,
            ],
        ];
    }

    private function splitName(string $fullName): array
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($fullName)) ?: []));
        $first = array_shift($parts) ?: 'PASSAGEIRO';
        $last = count($parts) > 0 ? array_pop($parts) : $first;
        $middle = count($parts) > 0 ? implode(' ', $parts) : null;

        return [$first, $middle, $last];
    }

    private function splitPhone(string $phone): array
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        return [
            'ddi' => '55',
            'ddd' => strlen($digits) >= 10 ? substr($digits, 0, 2) : '',
            'number' => strlen($digits) >= 10 ? substr($digits, 2) : $digits,
        ];
    }

    private function ageRange(?Carbon $birthDate): string
    {
        if (! $birthDate) {
            return 'ADT';
        }

        $age = $birthDate->age;

        return match (true) {
            $age <= 1 => 'INF',
            $age < 12 => 'CHD',
            default => 'ADT',
        };
    }

    private function paymentPayload(): array
    {
        $raw = trim((string) Setting::get('travellink_payment_payload', ''));
        if ($raw === '') {
            return [];
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            throw new \RuntimeException('JSON de pagamento Travellink inválido.');
        }

        return $payload;
    }

    private function extractLocalizador(array $response): ?string
    {
        $reservas = $response['Reservas'] ?? [];
        if (! is_array($reservas) || empty($reservas[0]) || ! is_array($reservas[0])) {
            return null;
        }

        return ! empty($reservas[0]['Localizador']) ? (string) $reservas[0]['Localizador'] : null;
    }

    private function extractTickets(array $response): array
    {
        $tickets = [];
        foreach ($response['Bilhetes'] ?? [] as $ticket) {
            if (is_array($ticket) && ! empty($ticket['Numero'])) {
                $tickets[] = (string) $ticket['Numero'];
            }
        }

        return $tickets;
    }

    private function removeNulls(array $payload): array
    {
        return array_filter($payload, fn ($value): bool => $value !== null && $value !== '');
    }
}
