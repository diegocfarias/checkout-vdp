<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\CustomerAuditLog;

class CustomerObserver
{
    private array $auditableFields = [
        'name',
        'email',
        'document',
        'phone',
        'google_id',
        'status',
    ];

    public function updating(Customer $customer): void
    {
        foreach ($this->auditableFields as $field) {
            if (! $customer->isDirty($field)) {
                continue;
            }

            $actorType = 'system';
            $actorId = null;

            if (auth('web')->check()) {
                $actorType = 'admin';
                $actorId = auth('web')->id();
            } elseif (auth('customer')->check()) {
                $actorType = 'customer';
                $actorId = auth('customer')->id();
            }

            CustomerAuditLog::create([
                'customer_id' => $customer->id,
                'field' => $field,
                'old_value' => $customer->getOriginal($field),
                'new_value' => $customer->getAttribute($field),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'ip_address' => request()->ip(),
            ]);
        }
    }
}
