<?php

namespace Tests\Feature;

use App\Services\BotpressNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotpressNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_does_nothing_without_webhook_url(): void
    {
        config()->set('services.botpress.webhook_url', null);
        Http::fake();

        BotpressNotifier::send('conversation-1', 'user-1', 'Mensagem');

        Http::assertNothingSent();
    }

    public function test_send_posts_payload_to_configured_webhook(): void
    {
        config()->set('services.botpress.webhook_url', 'https://botpress.test/webhook');

        Http::fake([
            'https://botpress.test/webhook' => Http::response(['ok' => true]),
        ]);

        BotpressNotifier::send('conversation-1', 'user-1', 'Mensagem');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://botpress.test/webhook'
                && $request['conversationId'] === 'conversation-1'
                && $request['userId'] === 'user-1'
                && $request['webhook'] === 'checkout'
                && $request['message'] === 'Mensagem';
        });
    }
}
