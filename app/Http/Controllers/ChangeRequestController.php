<?php

namespace App\Http\Controllers;

use App\Models\CustomerChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChangeRequestController extends Controller
{
    public function store(Request $request)
    {
        /** @var \App\Models\Customer $customer */
        $customer = auth('customer')->user();
        $field = $request->input('field');

        $rules = [
            'field' => ['required', 'string', 'in:email,document'],
            'requested_value' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];

        if ($field === 'email') {
            $rules['requested_value'][] = 'email';
            $rules['requested_value'][] = Rule::unique('customers', 'email')->ignore($customer->id);
        }

        if ($field === 'document') {
            $rules['requested_value'][] = 'regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/';
        }

        $validated = $request->validate($rules);
        $requestedValue = trim($validated['requested_value']);

        if ($field === 'email') {
            $requestedValue = strtolower($requestedValue);
        }

        if ($field === 'document') {
            $requestedValue = preg_replace('/\D/', '', $requestedValue);
        }

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
            'requested_value' => $requestedValue,
            'reason' => $validated['reason'] ?? null,
        ]);

        return back()->with('status', 'Solicitação enviada. Você será notificado quando for analisada.');
    }
}
