<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAuditLog;
use App\Models\CustomerChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    /**
     * Busca ou cria um cliente a partir dos dados do pagador.
     * Usado no checkout para vincular o pedido a um cliente.
     */
    public function findOrCreateFromPayer(array $payerData): Customer
    {
        $email = $payerData['email'];
        $customer = Customer::where('email', $email)->first();

        if ($customer) {
            return $customer;
        }

        $customer = Customer::create([
            'name' => $payerData['name'],
            'email' => $email,
            'document' => $payerData['document'] ?? null,
            'status' => 'pending',
        ]);

        Log::info('CustomerService: cliente criado automaticamente via checkout', [
            'customer_id' => $customer->id,
            'email' => $email,
        ]);

        return $customer;
    }

    /**
     * Vincula conta Google a um cliente existente.
     */
    public function linkGoogleAccount(Customer $customer, $googleUser): void
    {
        $customer->update([
            'google_id' => $googleUser->getId(),
            'avatar_url' => $googleUser->getAvatar(),
            'status' => 'active',
        ]);
    }

    /**
     * Aplica uma solicitacao de alteracao aprovada pelo admin.
     */
    public function applyChangeRequest(CustomerChangeRequest $request, User $admin): void
    {
        $customer = $request->customer;
        $field = $request->field;
        $oldValue = $customer->getAttribute($field);

        $customer->update([
            $field => $request->requested_value,
        ]);

        $request->update([
            'status' => 'approved',
            'admin_id' => $admin->id,
            'resolved_at' => now(),
        ]);

        CustomerAuditLog::create([
            'customer_id' => $customer->id,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $request->requested_value,
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'ip_address' => request()->ip(),
        ]);
    }
}
