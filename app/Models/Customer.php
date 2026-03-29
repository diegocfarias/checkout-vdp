<?php

namespace App\Models;

use App\Notifications\CustomerResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'document',
        'phone',
        'google_id',
        'avatar_url',
        'status',
        'is_affiliate',
        'referral_code',
        'affiliate_discount_pct',
        'affiliate_credit_pct',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_affiliate' => 'boolean',
            'affiliate_discount_pct' => 'decimal:2',
            'affiliate_credit_pct' => 'decimal:2',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(CustomerAuditLog::class)->orderByDesc('created_at');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(CustomerChangeRequest::class)->orderByDesc('created_at');
    }

    public function savedPassengers(): HasMany
    {
        return $this->hasMany(SavedPassenger::class)->orderBy('full_name');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'affiliate_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasPassword(): bool
    {
        return ! is_null($this->password);
    }

    public function isAffiliate(): bool
    {
        return $this->is_affiliate && $this->referral_code;
    }

    public function generateReferralCode(): string
    {
        do {
            $code = 'IND-' . strtoupper(Str::random(6));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    public function getCleanDocument(): ?string
    {
        return $this->document ? preg_replace('/\D/', '', $this->document) : null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPassword($token));
    }
}
