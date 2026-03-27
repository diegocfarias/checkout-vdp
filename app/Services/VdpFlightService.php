<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VdpFlightService
{
    public function searchAndFilter(array $payload): array
    {
        $ida = $payload['ida'];
        $volta = ! empty($payload['volta']) ? $payload['volta'] : null;

        $basePax = [
            'adults' => (int) $payload['total_adults'],
            'children' => (int) $payload['total_children'],
            'infants' => (int) $payload['total_babies'],
            'cabin' => $payload['cabin'],
            'departure' => $payload['departure_iata'],
            'arrival' => $payload['arrival_iata'],
            'outbound_date' => $ida['outbound_date'],
            'inbound_date' => $volta['inbound_date'] ?? null,
        ];

        $sameCia = $volta && $ida['cia'] === $volta['cia'];
        $outboundFlight = null;
        $inboundFlight = null;

        if (! $volta || $sameCia) {
            $response = $this->callApi(array_merge($basePax, ['cia' => $ida['cia']]));

            $outboundFlight = $this->findByUniqueId($response['outbound'] ?? [], $ida['unique_id']);

            if ($volta) {
                $inboundFlight = $this->findByUniqueId($response['inbound'] ?? [], $volta['unique_id']);
            }
        } else {
            $outboundResponse = $this->callApi(array_merge($basePax, ['cia' => $ida['cia']]));
            $outboundFlight = $this->findByUniqueId($outboundResponse['outbound'] ?? [], $ida['unique_id']);

            $inboundResponse = $this->callApi(array_merge($basePax, ['cia' => $volta['cia']]));
            $inboundFlight = $this->findByUniqueId($inboundResponse['inbound'] ?? [], $volta['unique_id']);
        }

        if (! $outboundFlight) {
            throw new \RuntimeException('Voo de ida não encontrado para o unique_id informado.');
        }

        if ($volta && ! $inboundFlight) {
            throw new \RuntimeException('Voo de volta não encontrado para o unique_id informado.');
        }

        $flights = [
            [
                'direction' => 'outbound',
                'cia' => $ida['cia'],
                'miles_price' => $this->parseMoneyValue($ida['miles_price']),
                'money_price' => $this->parseMoneyValue($ida['money_price']),
                'tax' => $this->parseMoneyValue($ida['tax']),
                ...$this->mapFlightData($outboundFlight),
            ],
        ];

        if ($volta && $inboundFlight) {
            $flights[] = [
                'direction' => 'inbound',
                'cia' => $volta['cia'],
                'miles_price' => $this->parseMoneyValue($volta['miles_price']),
                'money_price' => $this->parseMoneyValue($volta['money_price']),
                'tax' => $this->parseMoneyValue($volta['tax']),
                ...$this->mapFlightData($inboundFlight),
            ];
        }

        return $flights;
    }

    /**
     * Busca voos com cache de 30 minutos.
     */
    public function searchFlights(array $params): array
    {
        $cacheKey = 'vdp_search:' . md5(json_encode($params));

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($params) {
            return $this->callApi($params);
        });
    }

    /**
     * Busca voos direto na API (sem cache) para revalidação de preço.
     */
    public function searchFlightsFresh(array $params): array
    {
        return $this->callApi($params);
    }

    /**
     * Re-consulta um voo específico pelo unique_id e retorna o voo atualizado com preço atual.
     * Retorna null se o voo não existir mais.
     */
    public function revalidateFlight(array $searchParams, string $uniqueId, string $direction): ?array
    {
        try {
            $results = $this->searchFlightsFresh($searchParams);
            $flights = $results[$direction] ?? [];

            return $this->findByUniqueId($flights, $uniqueId);
        } catch (\Throwable $e) {
            Log::warning('VDP: falha ao revalidar voo', [
                'unique_id' => $uniqueId,
                'direction' => $direction,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Revalida ida e volta em 1 chamada (mesma cia) ou 2 chamadas (cias diferentes).
     *
     * @return array{outbound: ?array, inbound: ?array}
     */
    public function revalidateFlightPair(
        array $baseParams,
        string $obUniqueId,
        string $obCia,
        ?string $ibUniqueId = null,
        ?string $ibCia = null,
    ): array {
        $obParams = array_merge($baseParams, ['cia' => strtolower($obCia)]);

        $sameCia = $ibCia && strtolower($obCia) === strtolower($ibCia);

        try {
            $obResults = $this->searchFlightsFresh($obParams);
            $freshOb = $this->findByUniqueId($obResults['outbound'] ?? [], $obUniqueId);

            $freshIb = null;
            if ($ibUniqueId && $ibCia) {
                if ($sameCia) {
                    $freshIb = $this->findByUniqueId($obResults['inbound'] ?? [], $ibUniqueId);
                } else {
                    $ibParams = array_merge($baseParams, ['cia' => strtolower($ibCia)]);
                    $ibResults = $this->searchFlightsFresh($ibParams);
                    $freshIb = $this->findByUniqueId($ibResults['inbound'] ?? [], $ibUniqueId);
                }
            }

            return ['outbound' => $freshOb, 'inbound' => $freshIb];
        } catch (\Throwable $e) {
            Log::warning('VDP: falha ao revalidar par de voos', [
                'ob_unique_id' => $obUniqueId,
                'ib_unique_id' => $ibUniqueId,
                'error' => $e->getMessage(),
            ]);

            return ['outbound' => null, 'inbound' => null];
        }
    }

    private function callApi(array $params): array
    {
        $url = rtrim(config('services.vdp.url'), '/') . '/api/search/flights';

        Log::info('VDP API request', ['url' => $url, 'params' => $params]);

        $response = Http::acceptJson()
            ->timeout(30)
            ->post($url, $params);

        if ($response->failed()) {
            Log::error('VDP API retornou erro', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'request_params' => $params,
                'url' => $url,
            ]);

            throw new \RuntimeException(
                "VDP API retornou status {$response->status()}"
            );
        }

        return $response->json();
    }

    private function findByUniqueId(array $flights, string $uniqueId): ?array
    {
        foreach ($flights as $flight) {
            if (($flight['unique_id'] ?? null) === $uniqueId) {
                return $flight;
            }
        }

        return null;
    }

    /**
     * Converte valor monetário em formato BR ("1.151" ou "1.151,50") para decimal padrão ("1151" ou "1151.50").
     */
    public function parseMoneyValue(string $value): string
    {
        $value = trim($value);

        if (str_contains($value, ',')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        if (preg_match('/\.\d{3}$/', $value)) {
            return str_replace('.', '', $value);
        }

        return $value;
    }

    /**
     * Calcula o preço total de um voo (base + taxa) usando a precificação configurada.
     * Prioridade: milhas > percentual > preço original da API.
     */
    public function calculateFlightPrice(array $flight): float
    {
        $cia = $this->normalizeCia($flight['operator'] ?? '');
        $tax = $this->parseMoneyFloat($flight['boarding_tax'] ?? '0');

        $milesEnabled = Setting::get('pricing_miles_enabled', false);
        $pctEnabled = Setting::get('pricing_pct_enabled', false);

        if ($milesEnabled) {
            $miles = $this->parseMilesValue($flight['price_miles'] ?? null);
            if ($miles > 0) {
                $valorMilheiro = (float) Setting::get("pricing_miles_{$cia}", 30);
                $price = ($miles / 1000) * $valorMilheiro + $tax;

                Log::debug('Pricing: milhas', [
                    'cia' => $cia, 'miles' => $miles,
                    'valor_milheiro' => $valorMilheiro, 'tax' => $tax, 'price' => $price,
                ]);

                return $price;
            }
        }

        if ($pctEnabled) {
            $money = $this->parseMoneyFloat($flight['price_money'] ?? '0');
            $pct = (float) Setting::get("pricing_pct_{$cia}", 100);
            $price = $money * ($pct / 100) + $tax;

            Log::debug('Pricing: percentual', [
                'cia' => $cia, 'money' => $money, 'pct' => $pct,
                'tax' => $tax, 'price' => $price,
                'miles_raw' => $flight['price_miles'] ?? null,
            ]);

            return $price;
        }

        $money = $this->parseMoneyFloat($flight['price_money'] ?? '0');

        return $money + $tax;
    }

    /**
     * Calcula o preço base de um voo (sem taxa) para gravar no pedido.
     */
    public function calculateBasePrice(array $flight): string
    {
        $cia = $this->normalizeCia($flight['operator'] ?? '');

        $milesEnabled = Setting::get('pricing_miles_enabled', false);
        $pctEnabled = Setting::get('pricing_pct_enabled', false);

        if ($milesEnabled) {
            $miles = $this->parseMilesValue($flight['price_miles'] ?? null);
            if ($miles > 0) {
                $valorMilheiro = (float) Setting::get("pricing_miles_{$cia}", 30);

                return number_format(($miles / 1000) * $valorMilheiro, 2, '.', '');
            }
        }

        if ($pctEnabled) {
            $money = $this->parseMoneyFloat($flight['price_money'] ?? '0');
            $pct = (float) Setting::get("pricing_pct_{$cia}", 100);

            return number_format($money * ($pct / 100), 2, '.', '');
        }

        return $this->parseMoneyValue($flight['price_money'] ?? '0');
    }

    /**
     * Converte price_miles da API para float. Aceita formatos como "10.500", "10500", "10,500", etc.
     */
    private function parseMilesValue(mixed $value): float
    {
        if ($value === null || $value === '' || $value === '0') {
            return 0;
        }

        $str = (string) $value;
        $clean = preg_replace('/[^\d.,]/', '', $str);

        if ($clean === '' || $clean === '0') {
            return 0;
        }

        return $this->parseMoneyFloat($clean);
    }

    private function parseMoneyFloat(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        return (float) str_replace(['.', ','], ['', '.'], $value);
    }

    private function normalizeCia(string $operator): string
    {
        $map = [
            'gol' => 'gol', 'g3' => 'gol',
            'azul' => 'azul', 'ad' => 'azul',
            'latam' => 'latam', 'la' => 'latam', 'jj' => 'latam',
        ];

        return $map[strtolower(trim($operator))] ?? strtolower(trim($operator));
    }

    private function mapFlightData(array $flight): array
    {
        return [
            'operator' => $flight['operator'] ?? null,
            'flight_number' => $flight['flight_number'] ?? null,
            'departure_time' => $flight['departure_time'] ?? null,
            'arrival_time' => $flight['arrival_time'] ?? null,
            'departure_location' => $flight['departure_location'] ?? null,
            'arrival_location' => $flight['arrival_location'] ?? null,
            'departure_label' => $flight['departure_label'] ?? null,
            'arrival_label' => $flight['arrival_label'] ?? null,
            'boarding_tax' => $flight['boarding_tax'] ?? null,
            'class_service' => $flight['class_service'] ?? null,
            'price_money' => $flight['price_money'] ?? null,
            'price_miles' => $flight['price_miles'] ?? null,
            'price_miles_vip' => $flight['price_miles_vip'] ?? null,
            'total_flight_duration' => $flight['total_flight_duration'] ?? null,
            'unique_id' => $flight['unique_id'] ?? null,
            'connection' => $flight['connection'] ?? null,
        ];
    }
}
