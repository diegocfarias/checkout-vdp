<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'max_discount',
        'usage_limit',
        'usage_count',
        'active',
        'cumulative_with_pix',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'active' => 'boolean',
        'cumulative_with_pix' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'coupon_customer');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isValid(): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se o cupom pode ser usado pelo documento (CPF) informado.
     * Se não houver restrição de clientes, qualquer um pode usar.
     */
    public function isAvailableForDocument(?string $document): bool
    {
        if ($this->customers()->count() === 0) {
            return true;
        }

        if (! $document) {
            return false;
        }

        $cleanDoc = preg_replace('/\D/', '', $document);

        return $this->customers()
            ->whereRaw("REPLACE(REPLACE(document, '.', ''), '-', '') = ?", [$cleanDoc])
            ->exists();
    }

    public function calculateDiscount(float $baseTotal): float
    {
        if ($this->type === 'fixed') {
            return min((float) $this->value, $baseTotal);
        }

        $discount = $baseTotal * ((float) $this->value / 100);

        if ($this->max_discount !== null && $discount > (float) $this->max_discount) {
            $discount = (float) $this->max_discount;
        }

        return round(min($discount, $baseTotal), 2);
    }

    public function hasBeenUsed(): bool
    {
        return $this->usage_count > 0;
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
