<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SavedPassenger;
use App\Services\ReferralService;
use Illuminate\Http\Request;

class CustomerAreaController extends Controller
{
    public function dashboard()
    {
        $customer = auth('customer')->user();
        $recentOrders = Order::where('customer_id', $customer->id)
            ->with(['flights', 'flightSearch'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('customer.dashboard', compact('customer', 'recentOrders'));
    }

    public function orders()
    {
        $customer = auth('customer')->user();
        $orders = Order::where('customer_id', $customer->id)
            ->with(['flights', 'flightSearch'])
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('customer.orders', compact('customer', 'orders'));
    }

    public function orderDetail(Order $order)
    {
        $customer = auth('customer')->user();

        if ($order->customer_id !== $customer->id) {
            abort(404);
        }

        $order->load(['flights', 'passengers', 'payments', 'flightSearch', 'coupon']);

        return view('customer.order-detail', compact('customer', 'order'));
    }

    public function profile()
    {
        $customer = auth('customer')->user();

        return view('customer.profile', compact('customer'));
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|min:8|max:20',
        ]);

        /** @var \App\Models\Customer $customer */
        $customer = auth('customer')->user();
        $customer->update($request->only('name', 'phone'));

        return back()->with('status', 'Perfil atualizado com sucesso.');
    }

    public function passengers()
    {
        /** @var \App\Models\Customer $customer */
        $customer = auth('customer')->user();
        $savedPassengers = $customer->savedPassengers;

        return view('customer.passengers', compact('customer', 'savedPassengers'));
    }

    public function referrals(ReferralService $referralService)
    {
        /** @var \App\Models\Customer $customer */
        $customer = auth('customer')->user();

        if (! $customer->isAffiliate()) {
            abort(404);
        }

        $availableBalance = $referralService->getAvailableBalance($customer);
        $pendingBalance = $referralService->getPendingBalance($customer);

        $referrals = $customer->referrals()
            ->with('referredOrder')
            ->orderByDesc('created_at')
            ->paginate(15);

        $walletHistory = $customer->walletTransactions()
            ->with('order')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $referralLink = url('/?ref=' . $customer->referral_code);

        return view('customer.referrals', compact(
            'customer',
            'availableBalance',
            'pendingBalance',
            'referrals',
            'walletHistory',
            'referralLink',
        ));
    }

    public function destroyPassenger(SavedPassenger $savedPassenger)
    {
        /** @var \App\Models\Customer $customer */
        $customer = auth('customer')->user();

        if ($savedPassenger->customer_id !== $customer->id) {
            abort(404);
        }

        $savedPassenger->delete();

        return back()->with('status', 'Passageiro removido com sucesso.');
    }
}
