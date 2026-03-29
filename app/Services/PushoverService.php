<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushoverService
{
    public function send(string $message, ?string $device = null, array $options = []): bool
    {
        $token = Setting::get('pushover_app_token') ?: config('services.pushover.token');
        $userKey = Setting::get('pushover_user_key') ?: config('services.pushover.user_key');

        if (! $token || ! $userKey) {
            Log::warning('PushoverService: token ou user_key não configurados');
            return false;
        }

        $payload = array_merge([
            'token' => $token,
            'user' => $userKey,
            'message' => $message,
        ], $options);

        if ($device) {
            $payload['device'] = $device;
        }

        try {
            $response = Http::timeout(10)->post('https://api.pushover.net/1/messages.json', $payload);

            if (! $response->successful()) {
                Log::warning('PushoverService: resposta não-200', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('PushoverService: falha ao enviar', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function notifyIssuers(Order $order): void
    {
        $issuers = User::issuers()->withPushover()->get();

        if ($issuers->isEmpty()) {
            Log::info('PushoverService: nenhum emissor com Pushover configurado');
            return;
        }

        $order->loadMissing(['flights', 'flightSearch', 'passengers']);

        $route = $order->departure_iata . ' → ' . $order->arrival_iata;
        $passenger = $order->passengers->first();
        $passengerName = $passenger ? $passenger->full_name : 'N/A';

        $flightDate = '';
        if ($order->flightSearch) {
            $flightDate = $order->flightSearch->outbound_date
                ? $order->flightSearch->outbound_date->format('d/m/Y')
                : '';
        }

        $totalMiles = $order->flights->sum(function ($f) {
            return (float) ($f->price_miles ?? $f->miles_price ?? 0);
        });

        $milesFormatted = $totalMiles > 0 ? number_format($totalMiles, 0, '', '.') : 'N/A';

        $message = "Nova emissão pendente!\n"
            . "Pedido: {$order->tracking_code}\n"
            . "Rota: {$route}\n"
            . "Data: {$flightDate}\n"
            . "Passageiro: {$passengerName}\n"
            . "Milhas: {$milesFormatted}";

        foreach ($issuers as $issuer) {
            $this->send($message, $issuer->pushover_device_id, [
                'title' => 'Voe de Primeira - Nova Emissão',
                'priority' => 1,
                'sound' => 'cashregister',
                'url' => url('/admin/emission-queue'),
                'url_title' => 'Ver fila de emissões',
            ]);
        }
    }
}
