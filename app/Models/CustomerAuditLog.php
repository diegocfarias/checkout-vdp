<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'field',
        'old_value',
        'new_value',
        'actor_type',
        'actor_id',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function actorName(): string
    {
        if ($this->actor_type === 'admin' && $this->actor_id) {
            return User::find($this->actor_id)?->name ?? 'Admin #' . $this->actor_id;
        }

        if ($this->actor_type === 'customer' && $this->actor_id) {
            return Customer::find($this->actor_id)?->name ?? 'Cliente #' . $this->actor_id;
        }

        return 'Sistema';
    }
}
