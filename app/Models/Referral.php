<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    protected $fillable = [
        'affiliate_id',
        'referred_order_id',
        'referred_customer_id',
        'referred_document',
        'referral_code_used',
        'order_base_total',
        'discount_pct',
        'discount_amount',
        'credit_pct',
        'credit_amount',
        'credit_status',
        'credit_available_at',
        'credit_released_at',
        'status',
    ];

    protected $casts = [
        'order_base_total' => 'decimal:2',
        'discount_pct' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'credit_pct' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'credit_available_at' => 'datetime',
        'credit_released_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'affiliate_id');
    }

    public function referredOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'referred_order_id');
    }

    public function referredCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function isPending(): bool
    {
        return $this->credit_status === 'pending';
    }

    public function isAvailable(): bool
    {
        return $this->credit_status === 'available';
    }

    public function isReversed(): bool
    {
        return $this->credit_status === 'reversed';
    }

    public function scopePendingRelease($query)
    {
        return $query->where('credit_status', 'pending')
            ->where('status', 'active')
            ->where('credit_available_at', '<=', now());
    }
}
