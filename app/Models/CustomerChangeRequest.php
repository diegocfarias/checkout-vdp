<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerChangeRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'field',
        'current_value',
        'requested_value',
        'reason',
        'status',
        'admin_id',
        'admin_notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function fieldLabel(): string
    {
        return match ($this->field) {
            'email' => 'E-mail',
            'document' => 'CPF',
            default => $this->field,
        };
    }
}
