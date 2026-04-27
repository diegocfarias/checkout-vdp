<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            \App\Http\Middleware\CaptureReferralCode::class,
        ]);
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->is('admin/*')) {
                return route('filament.admin.auth.login');
            }
            return route('customer.login');
        });
        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
            if ($request->is('admin/*')) {
                return route('filament.admin.pages.dashboard');
            }
            return route('customer.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            $message = 'Sua sessão expirou. Recarregue a página e tente novamente.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 419);
            }

            return redirect()
                ->back()
                ->with('global_error', $message);
        });
    })->create();
