<?php

namespace Tests\Feature;

use App\Services\UnsplashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnsplashServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('services.unsplash.access_key', null);
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_search_photos_returns_empty_without_access_key(): void
    {
        Http::fake();

        $this->assertSame([], app(UnsplashService::class)->searchPhotos('Rio de Janeiro'));
        Http::assertNothingSent();
    }

    public function test_search_photos_maps_results_and_uses_cache(): void
    {
        config()->set('services.unsplash.access_key', 'unsplash-key');

        Http::fake([
            'https://api.unsplash.com/search/photos*' => Http::response([
                'results' => [
                    [
                        'urls' => [
                            'regular' => 'https://img.test/regular.jpg',
                            'small' => 'https://img.test/small.jpg',
                            'thumb' => 'https://img.test/thumb.jpg',
                        ],
                        'user' => ['name' => 'Fotografo'],
                    ],
                    [
                        'urls' => [
                            'small' => 'https://img.test/only-small.jpg',
                        ],
                        'user' => [],
                    ],
                    [
                        'urls' => [],
                    ],
                ],
            ]),
        ]);

        $service = app(UnsplashService::class);

        $first = $service->searchPhotos('Rio turismo', 3);
        $second = $service->searchPhotos('Rio turismo', 3);

        $this->assertSame($first, $second);
        $this->assertCount(2, $first);
        $this->assertSame('https://img.test/regular.jpg', $first[0]['url']);
        $this->assertSame('https://img.test/small.jpg', $first[0]['thumb']);
        $this->assertSame('Fotografo / Unsplash', $first[0]['credit']);
        $this->assertSame('https://img.test/only-small.jpg', $first[1]['url']);
        $this->assertSame('Unsplash / Unsplash', $first[1]['credit']);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return $query['query'] === 'Rio turismo'
                && $query['orientation'] === 'landscape'
                && $query['per_page'] === '3'
                && $query['client_id'] === 'unsplash-key';
        });
    }

    public function test_search_city_photo_returns_first_photo_from_custom_query(): void
    {
        config()->set('services.unsplash.access_key', 'unsplash-key');

        Http::fake([
            'https://api.unsplash.com/search/photos*' => Http::response([
                'results' => [
                    [
                        'urls' => ['regular' => 'https://img.test/vitoria.jpg'],
                        'user' => ['name' => 'Autor'],
                    ],
                ],
            ]),
        ]);

        $photo = app(UnsplashService::class)->searchCityPhoto('Vitoria', 'praia vitoria');

        $this->assertSame('https://img.test/vitoria.jpg', $photo['url']);
        Http::assertSent(function ($request): bool {
            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return $query['query'] === 'praia vitoria'
                && $query['per_page'] === '1';
        });
    }

    public function test_search_photos_returns_empty_on_failed_response(): void
    {
        config()->set('services.unsplash.access_key', 'unsplash-key');

        Http::fake([
            'https://api.unsplash.com/search/photos*' => Http::response(['error' => true], 500),
        ]);

        $this->assertSame([], app(UnsplashService::class)->searchPhotos('erro'));
    }
}
