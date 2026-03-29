<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferralCode
{
    public function handle(Request $request, Closure $next): Response
    {
        $refCode = $request->query('ref');

        if ($refCode && Setting::get('referral_enabled', false)) {
            $code = strtoupper(trim($refCode));

            $exists = Customer::where('referral_code', $code)
                ->where('is_affiliate', true)
                ->exists();

            if ($exists) {
                $days = (int) Setting::get('referral_cookie_days', 30);

                $response = $next($request);

                if ($response instanceof Response) {
                    $response->headers->setCookie(
                        cookie('ref_code', $code, $days * 24 * 60)
                    );
                }

                return $response;
            }
        }

        return $next($request);
    }
}
