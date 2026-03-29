<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderEmission extends Model
{
    protected $fillable = [
        'order_id',
        'issuer_id',
        'assigned_at',
        'completed_at',
        'duration_seconds',
        'emission_value',
        'miles_cost_per_thousand',
        'status',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'emission_value' => 'decimal:2',
        'miles_cost_per_thousand' => 'decimal:2',
        'duration_seconds' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OrderEmissionLog::class)->orderBy('created_at');
    }

    public function calculateDuration(): ?int
    {
        if (! $this->assigned_at || ! $this->completed_at) {
            return null;
        }

        return (int) $this->assigned_at->diffInSeconds($this->completed_at);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForIssuer($query, int $issuerId)
    {
        return $query->where('issuer_id', $issuerId);
    }

    public function scopeCompletedBetween($query, $from, $to)
    {
        return $query->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to]);
    }
}
