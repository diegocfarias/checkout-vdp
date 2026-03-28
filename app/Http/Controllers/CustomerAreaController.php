<?php

namespace App\Http\Controllers;

use App\Models\Order;
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

        $order->load(['flights', 'passengers', 'payments', 'flightSearch']);

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
}
