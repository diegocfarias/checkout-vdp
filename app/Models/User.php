<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
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

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'issuer', 'support']);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isIssuer(): bool
    {
        return $this->role === 'issuer';
    }

    public function isSupport(): bool
    {
        return $this->role === 'support';
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

    public function scopeSupportAgents($query)
    {
        return $query->where('role', 'support')->where('is_active', true);
    }

    public function scopeWithPushover($query)
    {
        return $query->whereNotNull('pushover_user_key');
    }
}
