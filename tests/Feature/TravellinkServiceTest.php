<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Services\TravellinkService;
use App\Services\VdpFlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class TravellinkServiceTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
    }

    public function test_search_flights_returns_empty_when_disabled_or_missing_credentials(): void
    {
        Http::fake();

        $result = app(TravellinkService::class)->searchFlights([
            'departure' => 'BHZ',
            'arrival' => 'DXB',
            'outbound_date' => '2026-07-16',
        ]);

        $this->assertSame(['outbound' => [], 'inbound' => []], $result);
        Http::assertNothingSent();

        Setting::set('travellink_search_enabled', true, 'boolean');

        $result = app(TravellinkService::class)->searchFlights([
            'departure' => 'BHZ',
            'arrival' => 'DXB',
            'outbound_date' => '2026-07-16',
        ]);

        $this->assertSame(['outbound' => [], 'inbound' => []], $result);
        Http::assertNothingSent();
    }

    public function test_search_flights_sends_expected_payload_and_transforms_response(): void
    {
        $this->configureTravellink();

        Http::fake([
            'https://travellink.test/Aereo/Disponibilidade' => Http::response([
                'Exception' => null,
                'ViagensTrecho1' => [$this->travellinkTrip([
                    'Id' => 111,
                    'IdentificacaoDaViagem' => 'OUTBOUND-TOKEN',
                    'Preco' => [
                        'Taxa' => 100.25,
                        'TotalTaxaEmbarque' => 72.15,
                        'TotalTarifa' => 1200.40,
                    ],
                ])],
                'ViagensTrecho2' => [$this->travellinkTrip([
                    'Id' => 222,
                    'IdentificacaoDaViagem' => 'INBOUND-TOKEN',
                    'Preco' => [
                        'Taxa' => 90,
                        'TotalTaxaEmbarque' => 60,
                        'TotalTarifa' => 900,
                    ],
                ], [
                    [
                        'CiaOperacional' => ['CodigoIata' => 'EK', 'Descricao' => 'Emirates'],
                        'Numero' => '247',
                        'HoraSaida' => '0805',
                        'HoraChegada' => '2155',
                        'Origem' => ['CodigoIata' => 'DXB', 'Descricao' => 'DUBAI'],
                        'Destino' => ['CodigoIata' => 'CNF', 'Descricao' => 'CONFINS'],
                        'Duracao' => '13:50',
                        'BagagemQuantidade' => 2,
                        'BagagemPeso' => 23,
                        'BagagemUnidadeDeMedida' => 'kg',
                        'Familia' => 'Economy Saver',
                    ],
                ])],
            ]),
        ]);

        $result = app(TravellinkService::class)->searchFlights([
            'departure' => 'BHZ',
            'arrival' => 'DXB',
            'outbound_date' => '2026-07-16',
            'inbound_date' => '2026-07-23',
            'adults' => 1,
            'children' => 1,
            'infants' => 0,
            'cabin' => 'EX',
        ], ['LATAM']);

        $outbound = $result['outbound'][0];
        $inbound = $result['inbound'][0];

        $this->assertSame('LATAM', $outbound['operator']);
        $this->assertSame('JJ4111', $outbound['flight_number']);
        $this->assertSame('19:10', $outbound['departure_time']);
        $this->assertSame('02:55', $outbound['arrival_time']);
        $this->assertSame('1200,40', $outbound['price_money']);
        $this->assertSame('100,25', $outbound['boarding_tax']);
        $this->assertSame('72,15', $outbound['airline_boarding_tax']);
        $this->assertSame('OUTBOUND-TOKEN', $outbound['provider_payload']['identificacao_da_viagem']);
        $this->assertSame(111, $outbound['provider_payload']['viagem_id']);
        $this->assertCount(2, $outbound['connection']);
        $this->assertSame('EK247', $inbound['flight_number']);
        $this->assertTrue($inbound['baggage']['checked']['included']);
        $this->assertSame(2, $inbound['baggage']['checked']['quantity']);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://travellink.test/Aereo/Disponibilidade'
                && $request->hasHeader('Developer-Token', 'dev-token')
                && $request->hasHeader('Developer-Access-Code', 'access-code')
                && $payload['Login'] === 'login'
                && $payload['Senha'] === 'secret'
                && $payload['Origem'] === 'BHZ'
                && $payload['Destino'] === 'DXB'
                && $payload['QuantidadeAdultos'] === 1
                && $payload['QuantidadeCriancas'] === 1
                && $payload['Cabine'] === 'C'
                && $payload['Sistema'] === 49
                && $payload['CompanhiasPreferenciais'] === ['LA', 'JJ']
                && str_starts_with($payload['DataIda'], '/Date(')
                && str_starts_with($payload['DataVolta'], '/Date(');
        });
    }

    public function test_vdp_service_exposes_travellink_as_global_provider_slot(): void
    {
        Setting::set('provider_gol', ['vdp'], 'json');
        Setting::set('provider_azul', [], 'json');
        Setting::set('provider_latam', [], 'json');
        Setting::set('travellink_search_enabled', true, 'boolean');

        $slots = app(VdpFlightService::class)->getActiveProviderSlots();

        $this->assertContains([
            'provider' => 'travellink',
            'airlines' => 'ALL',
            'patria' => false,
        ], $slots);
    }

    public function test_can_issue_order_requires_all_flights_from_travellink_with_payload(): void
    {
        Setting::set('travellink_emission_enabled', true, 'boolean');
        Setting::set('travellink_auto_emission_enabled', true, 'boolean');

        $order = \App\Models\Order::create([
            'total_adults' => 1,
            'total_children' => 0,
            'total_babies' => 0,
            'cabin' => 'EC',
            'departure_iata' => 'BHZ',
            'arrival_iata' => 'DXB',
            'status' => 'awaiting_emission',
            'expires_at' => now()->addHour(),
        ]);

        $order->flights()->create([
            'direction' => 'outbound',
            'cia' => 'LATAM',
            'operator' => 'LATAM',
            'source_provider' => 'travellink',
            'source_airlines' => 'ALL',
            'provider_payload' => [
                'viagem_id' => 111,
                'identificacao_da_viagem' => 'OUTBOUND-TOKEN',
            ],
        ]);
        $order->flights()->create([
            'direction' => 'inbound',
            'cia' => 'LATAM',
            'operator' => 'LATAM',
            'source_provider' => 'travellink',
            'source_airlines' => 'ALL',
            'provider_payload' => [
                'viagem_id' => 222,
                'identificacao_da_viagem' => 'INBOUND-TOKEN',
            ],
        ]);

        $service = app(TravellinkService::class);

        $this->assertTrue($service->canIssueOrder($order));
        $this->assertTrue($service->canAutoIssueOrder($order));

        $order->flights()->where('direction', 'inbound')->update([
            'source_provider' => 'bds_crawler',
        ]);

        $this->assertFalse($service->canIssueOrder($order->refresh()));
    }

    public function test_issue_order_dry_run_builds_summary_without_http_calls(): void
    {
        $this->configureTravellink();
        Setting::set('travellink_emission_enabled', true, 'boolean');
        Setting::set('travellink_dry_run', true, 'boolean');
        Http::fake();

        $order = $this->createTravellinkOrder();

        $result = app(TravellinkService::class)->issueOrder($order);

        $this->assertTrue($result['dry_run']);
        $this->assertSame(['Tarifar', 'Reservar', 'IniciarEmissao', 'Emitir'], $result['steps']);
        $this->assertSame([
            'classes' => 2,
            'passengers' => 1,
            'has_outbound' => true,
            'has_inbound' => true,
        ], $result['payload_summary']);
        Http::assertNothingSent();
    }

    public function test_tarifar_and_reservar_payloads_follow_travellink_contract(): void
    {
        $this->configureTravellink();
        Setting::set('travellink_emission_enabled', true, 'boolean');

        $order = $this->createTravellinkOrder();
        $service = app(TravellinkService::class);

        $tarifar = $service->tarifarPayload($order);
        $reservar = $service->reservarPayload($order);

        $this->assertSame(0, $tarifar['ClienteId']);
        $this->assertSame('OUTBOUND-TOKEN', $tarifar['IdentificacaoDaViagem']);
        $this->assertSame(111, $tarifar['ViagemIda']);
        $this->assertSame('INBOUND-TOKEN', $tarifar['IdentificacaoDaViagemVolta']);
        $this->assertSame(222, $tarifar['ViagemVolta']);
        $this->assertCount(2, $tarifar['ClassesSelecionadas']);
        $this->assertSame('4111', $tarifar['ClassesSelecionadas'][0]['NumeroDoVoo']);
        $this->assertTrue($tarifar['TarifarMelhorPreco']);

        $this->assertCount(1, $reservar['Passageiros']);
        $passenger = $reservar['Passageiros'][0];
        $this->assertSame('Maria', $passenger['Nome']);
        $this->assertSame('Silva', $passenger['Sobrenome']);
        $this->assertSame('ADT', $passenger['FaixaEtaria']);
        $this->assertSame('12345678900', $passenger['CPF']);
        $this->assertSame(1, $passenger['Documento']['Tipo']);
        $this->assertArrayNotHasKey('Validade', $passenger['Documento']);
        $this->assertSame('11', $passenger['Telefone']['NumeroDDD']);
        $this->assertSame('999999999', $passenger['Telefone']['NumeroTelefone']);
    }

    public function test_issue_order_calls_travellink_steps_and_extracts_result(): void
    {
        $this->configureTravellink();
        Setting::set('travellink_emission_enabled', true, 'boolean');
        Setting::set('travellink_dry_run', false, 'boolean');
        Setting::set('travellink_payment_payload', '{"FormaDePagamento":2}', 'string');

        $requestedServices = [];
        Http::fake(function ($request) use (&$requestedServices) {
            $requestedServices[] = $request->url();

            return match (true) {
                str_ends_with($request->url(), '/Tarifar') => Http::response([
                    'Exception' => null,
                    'Preco' => ['TotalGeral' => 1234.56],
                ]),
                str_ends_with($request->url(), '/Reservar') => Http::response([
                    'Exception' => null,
                    'Reservas' => [['Localizador' => 'abc123']],
                ]),
                str_ends_with($request->url(), '/IniciarEmissao') => Http::response([
                    'Exception' => null,
                    'ConfiguracoesDeEmissao' => [
                        'FoidObrigatorio' => false,
                        'ExigirChaveDeSeguranca' => false,
                    ],
                ]),
                str_ends_with($request->url(), '/Emitir') => Http::response([
                    'Exception' => null,
                    'Bilhetes' => [['Numero' => '0011234567890']],
                ]),
                default => Http::response(['Exception' => 'servico inesperado'], 400),
            };
        });

        $result = app(TravellinkService::class)->issueOrder($this->createTravellinkOrder());

        $this->assertFalse($result['dry_run']);
        $this->assertSame('abc123', $result['localizador']);
        $this->assertSame(['0011234567890'], $result['tickets']);
        $this->assertSame([
            'https://travellink.test/Aereo/Tarifar',
            'https://travellink.test/Aereo/Reservar',
            'https://travellink.test/Aereo/IniciarEmissao',
            'https://travellink.test/Aereo/Emitir',
        ], $requestedServices);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return str_ends_with($request->url(), '/Emitir')
                && $payload['Localizador'] === 'abc123'
                && $payload['Pagamento'] === ['FormaDePagamento' => 2];
        });
    }

    private function configureTravellink(): void
    {
        Setting::set('travellink_search_enabled', true, 'boolean');
        Setting::set('travellink_base_url', 'https://travellink.test/Aereo', 'string');
        Setting::set('travellink_login', 'login', 'string');
        Setting::set('travellink_password', 'secret', 'string');
        Setting::set('travellink_developer_token', 'dev-token', 'string');
        Setting::set('travellink_developer_access_code', 'access-code', 'string');
        Setting::set('travellink_client_id', 0, 'integer');
        Setting::set('travellink_system', 49, 'integer');
        Setting::set('travellink_max_flights', 20, 'integer');
        Setting::set('travellink_timeout', 12, 'integer');
    }

    private function createTravellinkOrder(): Order
    {
        $order = $this->createOrder([
            'status' => 'awaiting_emission',
            'departure_iata' => 'BHZ',
            'arrival_iata' => 'DXB',
        ]);

        $this->addPassenger($order, [
            'full_name' => 'Maria Silva',
            'document' => '123.456.789-00',
            'birth_date' => '1990-01-01',
            'email' => 'maria@example.com',
            'phone' => '+55 (11) 99999-9999',
        ]);

        $this->addFlight($order, [
            'direction' => 'outbound',
            'cia' => 'LATAM',
            'operator' => 'LATAM',
            'flight_number' => 'JJ4111',
            'unique_id' => 'travellink-outbound',
            'source_provider' => 'travellink',
            'source_airlines' => 'ALL',
            'tax' => '72.15',
            'boarding_tax' => '72,15',
            'provider_payload' => [
                'viagem_id' => 111,
                'identificacao_da_viagem' => 'OUTBOUND-TOKEN',
                'classes_selecionadas' => [[
                    'BaseTarifaria' => 'ABC',
                    'Classe' => 'Y',
                    'Familia' => 'LIG',
                    'NumeroDoVoo' => '4111',
                ]],
            ],
        ]);

        $this->addFlight($order, [
            'direction' => 'inbound',
            'cia' => 'LATAM',
            'operator' => 'LATAM',
            'flight_number' => 'ET0613',
            'unique_id' => 'travellink-inbound',
            'source_provider' => 'travellink',
            'source_airlines' => 'ALL',
            'tax' => '60.00',
            'boarding_tax' => '60,00',
            'provider_payload' => [
                'viagem_id' => 222,
                'identificacao_da_viagem' => 'INBOUND-TOKEN',
                'classes_selecionadas' => [[
                    'BaseTarifaria' => 'DEF',
                    'Classe' => 'Y',
                    'Familia' => 'LIG',
                    'NumeroDoVoo' => '0613',
                ]],
            ],
        ]);

        return $order->refresh();
    }

    private function travellinkTrip(array $overrides = [], ?array $segments = null): array
    {
        return array_merge([
            'Id' => 123,
            'IdentificacaoDaViagem' => 'TRIP-TOKEN',
            'CiaMandatoria' => ['CodigoIata' => 'JJ', 'Descricao' => 'Tam'],
            'Fornecedor' => ['Codigo' => 70, 'Nome' => 'Fornecedor'],
            'Duracao' => 1745,
            'Preco' => [
                'Taxa' => 100,
                'TotalTaxaEmbarque' => 70,
                'TotalTarifa' => 1200,
            ],
            'Voos' => $segments ?? [
                [
                    'CiaOperacional' => ['CodigoIata' => 'JJ', 'Descricao' => 'Tam'],
                    'Numero' => '4111',
                    'HoraSaida' => '1910',
                    'HoraChegada' => '2355',
                    'Origem' => ['CodigoIata' => 'CNF', 'Descricao' => 'CONFINS'],
                    'Destino' => ['CodigoIata' => 'ADD', 'Descricao' => 'ADDIS ABEBA'],
                    'Duracao' => '12:45',
                    'BagagemQuantidade' => 0,
                    'Familia' => 'Light',
                    'BaseTarifaria' => 'ABC',
                    'Classe' => 'Y',
                    'FamiliaCodigo' => 'LIG',
                ],
                [
                    'CiaOperacional' => ['CodigoIata' => 'ET', 'Descricao' => 'Ethiopian'],
                    'Numero' => '613',
                    'HoraSaida' => '0010',
                    'HoraChegada' => '0255',
                    'Origem' => ['CodigoIata' => 'ADD', 'Descricao' => 'ADDIS ABEBA'],
                    'Destino' => ['CodigoIata' => 'DXB', 'Descricao' => 'DUBAI'],
                    'Duracao' => '04:45',
                    'BagagemQuantidade' => 0,
                    'Familia' => 'Light',
                    'BaseTarifaria' => 'DEF',
                    'Classe' => 'Y',
                    'FamiliaCodigo' => 'LIG',
                ],
            ],
        ], $overrides);
    }
}
