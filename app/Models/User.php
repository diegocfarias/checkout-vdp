<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'pushover_user_key',
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isIssuer(): bool
    {
        return $this->role === 'issuer';
    }

    public function canIssue(): bool
    {
        return true;
    }

    public function emissions(): HasMany
    {
        return $this->hasMany(OrderEmission::class, 'issuer_id');
    }

    public function scopeIssuers($query)
    {
        return $query->whereIn('role', ['admin', 'issuer'])->where('is_active', true);
    }

    public function scopeWithPushover($query)
    {
        return $query->whereNotNull('pushover_user_key');
    }
}
