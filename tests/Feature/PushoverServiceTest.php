<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\PushoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesCheckoutFixtures;
use Tests\TestCase;

class PushoverServiceTest extends TestCase
{
    use CreatesCheckoutFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
        config()->set('services.pushover.token', null);
    }

    protected function tearDown(): void
    {
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_send_returns_false_without_token_or_user_key(): void
    {
        Http::fake();

        $service = app(PushoverService::class);

        $this->assertFalse($service->send('Mensagem', 'user-key'));
        $this->assertFalse($service->send('Mensagem', ''));
        Http::assertNothingSent();
    }

    public function test_send_posts_message_with_setting_token_and_options(): void
    {
        Setting::set('pushover_app_token', 'setting-token');

        Http::fake([
            'https://api.pushover.net/1/messages.json' => Http::response(['status' => 1]),
        ]);

        $result = app(PushoverService::class)->send('Mensagem teste', 'user-key', [
            'title' => 'Titulo',
            'priority' => 1,
        ]);

        $this->assertTrue($result);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.pushover.net/1/messages.json'
                && $request['token'] === 'setting-token'
                && $request['user'] === 'user-key'
                && $request['message'] === 'Mensagem teste'
                && $request['title'] === 'Titulo'
                && $request['priority'] === 1;
        });
    }

    public function test_send_returns_false_for_non_successful_response(): void
    {
        config()->set('services.pushover.token', 'config-token');

        Http::fake([
            'https://api.pushover.net/1/messages.json' => Http::response(['status' => 0], 500),
        ]);

        $this->assertFalse(app(PushoverService::class)->send('Mensagem', 'user-key'));
    }

    public function test_notify_issuers_sends_order_summary_to_active_issuers_with_pushover(): void
    {
        Setting::set('pushover_app_token', 'setting-token');
        config()->set('app.url', 'https://checkout.test');

        User::factory()->create([
            'role' => 'issuer',
            'is_active' => true,
            'pushover_user_key' => 'issuer-key',
        ]);
        User::factory()->create([
            'role' => 'issuer',
            'is_active' => false,
            'pushover_user_key' => 'inactive-key',
        ]);
        User::factory()->create([
            'role' => 'support',
            'is_active' => true,
            'pushover_user_key' => 'support-key',
        ]);

        $search = $this->createFlightSearch(['outbound_date' => '2026-06-10']);
        $order = $this->createOrder([
            'tracking_code' => 'VDP-TEST',
            'flight_search_id' => $search->id,
        ]);
        $this->addPassenger($order, ['full_name' => 'Maria Silva']);
        $this->addFlight($order, ['price_miles' => '8000']);

        Http::fake([
            'https://api.pushover.net/1/messages.json' => Http::response(['status' => 1]),
        ]);

        app(PushoverService::class)->notifyIssuers($order);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request['user'] === 'issuer-key'
                && str_contains($request['message'], 'VDP-TEST')
                && str_contains($request['message'], 'Maria Silva')
                && $request['title'] === 'Voe de Primeira - Nova Emissão';
        });
    }
}
