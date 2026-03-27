<?php

namespace App\Http\Controllers;

use App\Models\CustomerChangeRequest;
use Illuminate\Http\Request;

class ChangeRequestController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'field' => 'required|string|in:email,document',
            'requested_value' => 'required|string|max:255',
            'reason' => 'nullable|string|max:1000',
        ]);

        /** @var \App\Models\Customer $customer */
        $customer = auth('customer')->user();
        $field = $request->field;

        $pending = CustomerChangeRequest::where('customer_id', $customer->id)
            ->where('field', $field)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            return back()->withErrors(['field' => 'Você já possui uma solicitação pendente para este campo.']);
        }

        CustomerChangeRequest::create([
            'customer_id' => $customer->id,
            'field' => $field,
            'current_value' => $customer->getAttribute($field) ?? '',
            'requested_value' => $request->requested_value,
            'reason' => $request->reason,
        ]);

        return back()->with('status', 'Solicitação enviada. Você será notificado quando for analisada.');
    }
}
