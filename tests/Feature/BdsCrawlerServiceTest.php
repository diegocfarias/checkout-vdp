<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\BdsCrawlerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BdsCrawlerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
    }

    public function test_search_flights_returns_empty_when_url_is_missing(): void
    {
        config()->set('services.bds_crawler.url', '');
        Http::fake();

        $result = app(BdsCrawlerService::class)->searchFlights([
            'departure' => 'GRU',
            'arrival' => 'SDU',
            'outbound_date' => '2026-06-10',
        ]);

        $this->assertSame(['outbound' => [], 'inbound' => []], $result);
        Http::assertNothingSent();
    }

    public function test_search_flights_sends_expected_query_headers_and_returns_payload(): void
    {
        config()->set('services.bds_crawler.url', 'https://bds.test/');
        config()->set('services.bds_crawler.api_key', 'secret-key');
        Setting::set('bds_crawler_timeout', 12, 'integer');

        Http::fake([
            'https://bds.test/api/search*' => Http::response([
                'outbound' => [['operator' => 'AZUL', 'boarding_tax' => '99,73']],
                'inbound' => [['operator' => 'AZUL', 'boarding_tax' => '120,26']],
            ]),
        ]);

        $result = app(BdsCrawlerService::class)->searchFlights([
            'departure' => 'BHZ',
            'arrival' => 'VIX',
            'outbound_date' => '2026-06-11',
            'inbound_date' => '2026-06-15',
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'cabin' => 'EX',
        ], ['azul', 'latam']);

        $this->assertSame('99,73', $result['outbound'][0]['boarding_tax']);
        $this->assertSame('120,26', $result['inbound'][0]['boarding_tax']);

        Http::assertSent(function ($request): bool {
            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return str_starts_with($request->url(), 'https://bds.test/api/search?')
                && $request->hasHeader('X-API-KEY', 'secret-key')
                && $query['origin'] === 'BHZ'
                && $query['destination'] === 'VIX'
                && $query['outbound_date'] === '2026-06-11'
                && $query['inbound_date'] === '2026-06-15'
                && $query['adults'] === '2'
                && $query['children'] === '1'
                && $query['infants'] === '0'
                && $query['cabin'] === 'EX'
                && $query['airlines'] === 'AZUL,LATAM';
        });
    }

    public function test_search_flights_returns_empty_on_failed_response(): void
    {
        config()->set('services.bds_crawler.url', 'https://bds.test');
        config()->set('services.bds_crawler.api_key', 'secret-key');

        Http::fake([
            'https://bds.test/api/search*' => Http::response(['message' => 'erro'], 500),
        ]);

        $result = app(BdsCrawlerService::class)->searchFlights([
            'departure' => 'GRU',
            'arrival' => 'SDU',
            'outbound_date' => '2026-06-10',
        ]);

        $this->assertSame(['outbound' => [], 'inbound' => []], $result);
    }
}
