<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEmissionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_emission_id',
        'action',
        'user_id',
        'from_issuer_id',
        'to_issuer_id',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function emission(): BelongsTo
    {
        return $this->belongsTo(OrderEmission::class, 'order_emission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromIssuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_issuer_id');
    }

    public function toIssuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_issuer_id');
    }
}
