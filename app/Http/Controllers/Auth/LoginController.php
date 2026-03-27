<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if ($customer && ! $customer->hasPassword()) {
            return back()->withErrors([
                'email' => 'Esta conta ainda não possui senha definida. Use o link abaixo para criar uma.',
            ])->withInput()->with('needs_password', true);
        }

        if (! Auth::guard('customer')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'E-mail ou senha incorretos.',
            ])->withInput();
        }

        $request->session()->regenerate();

        /** @var \App\Models\Customer $customer */
        $customer = Auth::guard('customer')->user();

        if ($customer->isPending()) {
            $customer->update(['status' => 'active']);
        }

        return redirect()->intended(route('customer.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('search.home');
    }
}
