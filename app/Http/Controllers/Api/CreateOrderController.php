<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\BotpressNotifier;
use App\Services\VdpFlightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CreateOrderController extends Controller
{
    public function __invoke(StoreOrderRequest $request, VdpFlightService $vdpService): JsonResponse
    {
        $validated = $request->validated();

        try {
            $flights = $vdpService->searchAndFilter($validated);
        } catch (\RuntimeException $e) {
            Log::error('CreateOrder: falha na busca VDP', [
                'error' => $e->getMessage(),
                'userId' => $validated['userId'],
                'conversationId' => $validated['conversationId'],
                'ida_unique_id' => $validated['ida']['unique_id'] ?? null,
                'volta_unique_id' => $validated['volta']['unique_id'] ?? null,
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('CreateOrder: erro inesperado', [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'userId' => $validated['userId'],
                'conversationId' => $validated['conversationId'],
            ]);

            return response()->json(['message' => 'Erro ao buscar voos na API VDP.'], 502);
        }

        $order = Order::create([
            'total_adults' => $validated['total_adults'],
            'total_children' => $validated['total_children'],
            'total_babies' => $validated['total_babies'],
            'user_id' => $validated['userId'],
            'conversation_id' => $validated['conversationId'],
            'cabin' => $validated['cabin'],
            'departure_iata' => $validated['departure_iata'],
            'arrival_iata' => $validated['arrival_iata'],
            'expires_at' => now()->addMinutes(config('app.order_expiration_minutes')),
        ]);

        foreach ($flights as $flightData) {
            $order->flights()->create($flightData);
        }

        $link = rtrim(config('app.url'), '/') . "/r/{$order->token}";

        BotpressNotifier::send($validated['conversationId'], $validated['userId'], $link);

        return response()->json(['link' => $link], 201);
    }
}
