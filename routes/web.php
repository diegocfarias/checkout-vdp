<?php

use App\Http\Controllers\AbacatePayWebhookController;
use App\Http\Controllers\Admin\ShowcaseImageController;
use App\Http\Controllers\AirportController;
use App\Http\Controllers\AppMaxWebhookController;
use App\Http\Controllers\Auth\CompleteRegistrationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChangeRequestController;
use App\Http\Controllers\CustomerAreaController;
use App\Http\Controllers\FlightSearchController;
use App\Http\Controllers\OrderCheckoutController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Middleware\EnsureCustomerIsActive;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/', [FlightSearchController::class, 'index'])->name('search.home');

Route::post('/api/airports', AirportController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/voos', [FlightSearchController::class, 'search'])->name('search.results');
    Route::post('/voos/selecionar', [FlightSearchController::class, 'select'])->name('search.select');
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
        'miles_price' => '5000',
        'money_price' => '3.00',
        'tax' => '2.00',
    ]);

    return redirect('/r/' . $order->token);
})->name('dev.fake-checkout');

Route::post('/webhooks/appmax', AppMaxWebhookController::class)
    ->name('webhooks.appmax')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/webhooks/abacatepay', AbacatePayWebhookController::class)
    ->name('webhooks.abacatepay')
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

// ── Auth (customer) ──

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('customer.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('customer.google.callback');

Route::middleware('guest:customer')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('customer.login');
    Route::post('/login', [LoginController::class, 'login'])->name('customer.login.submit');
    Route::get('/registro', [RegisterController::class, 'showForm'])->name('customer.register');
    Route::post('/registro', [RegisterController::class, 'register'])->name('customer.register.submit');
    Route::get('/esqueci-senha', [ForgotPasswordController::class, 'show'])->name('customer.password.request');
    Route::post('/esqueci-senha', [ForgotPasswordController::class, 'send'])->name('customer.password.email');
    Route::get('/redefinir-senha/{token}', [PasswordController::class, 'showReset'])->name('customer.password.reset');
    Route::post('/redefinir-senha', [PasswordController::class, 'reset'])->name('customer.password.update');
});

Route::get('/completar-cadastro', [CompleteRegistrationController::class, 'show'])->name('customer.complete-registration');
Route::post('/completar-cadastro', [CompleteRegistrationController::class, 'store'])->name('customer.complete-registration.submit');

Route::middleware('auth:customer')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('customer.logout');
});

Route::middleware(['auth:customer', EnsureCustomerIsActive::class])->group(function () {
    Route::get('/minha-conta', [CustomerAreaController::class, 'dashboard'])->name('customer.dashboard');
    Route::get('/minha-conta/pedidos', [CustomerAreaController::class, 'orders'])->name('customer.orders');
    Route::get('/minha-conta/pedidos/{order}', [CustomerAreaController::class, 'orderDetail'])->name('customer.order.show');
    Route::get('/minha-conta/perfil', [CustomerAreaController::class, 'profile'])->name('customer.profile');
    Route::put('/minha-conta/perfil', [CustomerAreaController::class, 'updateProfile'])->name('customer.profile.update');
    Route::get('/minha-conta/passageiros', [CustomerAreaController::class, 'passengers'])->name('customer.passengers');
    Route::delete('/minha-conta/passageiros/{savedPassenger}', [CustomerAreaController::class, 'destroyPassenger'])->name('customer.passenger.destroy');
    Route::get('/minha-conta/indicacoes', [CustomerAreaController::class, 'referrals'])->name('customer.referrals');
    Route::post('/minha-conta/solicitar-alteracao', [ChangeRequestController::class, 'store'])->name('customer.change-request');
});

// ── Checkout & Tracking ──

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/r/{token}', [OrderCheckoutController::class, 'show'])->name('checkout.show');
    Route::get('/r/{token}/passageiros', [OrderCheckoutController::class, 'showPassengers'])->name('checkout.passengers');
    Route::post('/r/{token}/apply-coupon', [OrderCheckoutController::class, 'applyCoupon'])->name('checkout.apply-coupon');
    Route::post('/r/{order:token}', [OrderCheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/r/{order:token}/payment-callback', [OrderCheckoutController::class, 'paymentCallback'])->name('checkout.payment-callback');

    Route::get('/pedido', [OrderTrackingController::class, 'showForm'])->name('tracking.form');
    Route::post('/pedido', [OrderTrackingController::class, 'search'])->name('tracking.search');
    Route::get('/pedido/{trackingCode}', [OrderTrackingController::class, 'show'])->name('tracking.show');
});

Route::post('/admin/showcase/search-images', [ShowcaseImageController::class, 'search'])
    ->middleware(['web', 'auth'])
    ->name('admin.showcase.search-images');
