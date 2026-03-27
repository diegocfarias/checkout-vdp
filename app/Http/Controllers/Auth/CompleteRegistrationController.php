<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompleteRegistrationController extends Controller
{
    public function show(Request $request)
    {
        $googleUser = session('google_user');

        if (! $googleUser) {
            return redirect()->route('customer.login');
        }

        return view('auth.complete-registration', [
            'googleUser' => $googleUser,
        ]);
    }

    public function store(Request $request)
    {
        $googleUser = session('google_user');

        if (! $googleUser) {
            return redirect()->route('customer.login');
        }

        $request->validate([
            'document' => ['required', 'string', 'regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/'],
            'phone' => ['required', 'string', 'min:8'],
        ]);

        $customer = Customer::create([
            'name' => $googleUser['name'],
            'email' => $googleUser['email'],
            'google_id' => $googleUser['id'],
            'avatar_url' => $googleUser['avatar'],
            'document' => preg_replace('/\D/', '', $request->document),
            'phone' => $request->phone,
            'status' => 'active',
        ]);

        session()->forget('google_user');

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard');
    }
}
