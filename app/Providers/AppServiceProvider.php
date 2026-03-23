<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\PaymentGatewayResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayResolver::class);

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayResolver::class)->resolve();
        });
    }

    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Order::observe(OrderObserver::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(30)->by(
                $request->header('X-API-KEY') ?: $request->ip()
            );
        });
    }
}
