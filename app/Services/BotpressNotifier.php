<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotpressNotifier
{
    public static function send(string $conversationId, string $userId, string $message): void
    {
        $webhookUrl = config('services.botpress.webhook_url');

        if (! $webhookUrl) {
            return;
        }

        try {
            Http::timeout(10)->post($webhookUrl, [
                'conversationId' => $conversationId,
                'userId' => $userId,
                'webhook' => 'checkout',
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao notificar Botpress webhook', [
                'error' => $e->getMessage(),
                'conversationId' => $conversationId,
            ]);
        }
    }
}
