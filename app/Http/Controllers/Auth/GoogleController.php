<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
    ) {}

    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google OAuth: falha no callback', ['error' => $e->getMessage()]);

            return redirect()->route('customer.login')->withErrors([
                'google' => 'Não foi possível autenticar com o Google. Tente novamente.',
            ]);
        }

        $customer = Customer::where('google_id', $googleUser->getId())->first();

        if ($customer) {
            Auth::guard('customer')->login($customer, true);
            session()->regenerate();

            return redirect()->intended(route('customer.dashboard'));
        }

        $customer = Customer::where('email', $googleUser->getEmail())->first();

        if ($customer) {
            $this->customerService->linkGoogleAccount($customer, $googleUser);

            Auth::guard('customer')->login($customer, true);
            session()->regenerate();

            return redirect()->intended(route('customer.dashboard'));
        }

        session(['google_user' => [
            'id' => $googleUser->getId(),
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'avatar' => $googleUser->getAvatar(),
        ]]);

        return redirect()->route('customer.complete-registration');
    }
}
