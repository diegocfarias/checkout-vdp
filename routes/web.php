<?php

use App\Http\Controllers\AppMaxWebhookController;
use App\Http\Controllers\OrderCheckoutController;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Rota de desenvolvimento: cria pedido fake e redireciona para o checkout (apenas quando APP_DEBUG=true)
Route::get('/dev/fake-checkout', function () {
    if (! config('app.debug')) {
        abort(404);
    }

    $order = Order::create([
        'total_adults' => 1,
        'total_children' => 0,
        'total_babies' => 0,
        'cabin' => 'economy',
        'departure_iata' => 'GRU',
        'arrival_iata' => 'GIG',
        'status' => 'pending',
        'expires_at' => now()->addHours(24),
    ]);

    $order->flights()->create([
        'direction' => 'outbound',
        'cia' => 'G3',
        'flight_number' => '1234',
        'departure_time' => '08:00',
        'arrival_time' => '09:15',
        'departure_location' => 'São Paulo (GRU)',
        'arrival_location' => 'Rio de Janeiro (GIG)',
        'departure_label' => now()->format('d M Y'),
        'arrival_label' => now()->format('d M Y'),
        'total_flight_duration' => '1h15',
        'miles_price' => '15000',
        'money_price' => '299.00',
        'tax' => '89.00',
    ]);

    $order->flights()->create([
        'direction' => 'inbound',
        'cia' => 'G3',
        'flight_number' => '5678',
        'departure_time' => '18:00',
        'arrival_time' => '19:15',
        'departure_location' => 'Rio de Janeiro (GIG)',
        'arrival_location' => 'São Paulo (GRU)',
        'departure_label' => now()->addDays(5)->format('d M Y'),
        'arrival_label' => now()->addDays(5)->format('d M Y'),
        'total_flight_duration' => '1h15',
        'miles_price' => '15000',
        'money_price' => '299.00',
        'tax' => '89.00',
    ]);

    return redirect('/r/' . $order->token);
})->name('dev.fake-checkout');

Route::post('/webhooks/appmax', AppMaxWebhookController::class)
    ->name('webhooks.appmax')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::match(['get', 'post'], '/appmax/validate', function (\Illuminate\Http\Request $request) {
    if ($request->isMethod('post')) {
        \Illuminate\Support\Facades\Log::info('AppMax: credenciais do merchant recebidas', [
            'app_id' => $request->input('app_id'),
            'client_id' => $request->input('client_id'),
            'client_secret' => $request->input('client_secret'),
            'external_key' => $request->input('external_key'),
        ]);

        return response()->json([
            'external_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    return response()->json(['status' => 'ok', 'app' => 'checkout-vdp']);
})->name('appmax.validate')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/r/{token}', [OrderCheckoutController::class, 'show'])->name('checkout.show');
    Route::get('/r/{token}/passageiros', [OrderCheckoutController::class, 'showPassengers'])->name('checkout.passengers');
    Route::post('/r/{order:token}', [OrderCheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/r/{order:token}/payment-callback', [OrderCheckoutController::class, 'paymentCallback'])->name('checkout.payment-callback');
});
