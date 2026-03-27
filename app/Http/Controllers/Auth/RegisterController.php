<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function showForm()
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        $existing = Customer::where('email', $request->email)->first();

        if ($existing && $existing->isPending()) {
            $existing->update([
                'name' => $request->name,
                'password' => $request->password,
                'document' => preg_replace('/\D/', '', $request->document ?? ''),
                'phone' => $request->phone,
                'status' => 'active',
            ]);

            Auth::guard('customer')->login($existing, true);
            $request->session()->regenerate();

            return redirect()->route('customer.dashboard');
        }

        $customer = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'document' => preg_replace('/\D/', '', $request->document ?? ''),
            'phone' => $request->phone,
            'status' => 'active',
        ]);

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard');
    }
}
