<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            return redirect()->route('customer.login');
        }

        if ($customer->isPending() && ! $customer->hasPassword()) {
            return redirect()->route('customer.password.request')
                ->with('status', 'Complete seu cadastro definindo uma senha para acessar sua conta.');
        }

        return $next($request);
    }
}
