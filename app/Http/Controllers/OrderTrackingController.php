<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    public function showForm()
    {
        return view('tracking.search');
    }

    public function search(Request $request)
    {
        $request->validate([
            'tracking_code' => 'required|string|max:10',
            'document' => 'required|string|max:20',
        ]);

        $trackingCode = strtoupper(trim($request->input('tracking_code')));
        $document = preg_replace('/\D/', '', $request->input('document'));

        $order = Order::where('tracking_code', $trackingCode)
            ->whereHas('passengers', function ($q) use ($document) {
                $q->whereRaw("REPLACE(REPLACE(REPLACE(document, '.', ''), '-', ''), '/', '') = ?", [$document]);
            })
            ->first();

        if (! $order) {
            return back()
                ->withInput()
                ->withErrors(['tracking_code' => 'Pedido não encontrado. Verifique o código e o CPF informados.']);
        }

        session(["tracking_verified_{$order->tracking_code}" => true]);

        return redirect()->route('tracking.show', $order->tracking_code);
    }

    public function show(string $trackingCode)
    {
        $trackingCode = strtoupper($trackingCode);

        if (! session("tracking_verified_{$trackingCode}")) {
            return redirect()->route('tracking.form')
                ->withErrors(['tracking_code' => 'Informe o código do pedido e CPF para acessar.']);
        }

        $order = Order::where('tracking_code', $trackingCode)
            ->with(['flights', 'statusHistories', 'latestPayment'])
            ->firstOrFail();

        return view('tracking.show', ['order' => $order]);
    }
}
