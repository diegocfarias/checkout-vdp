<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'token',
        'tracking_code',
        'total_adults',
        'total_children',
        'total_babies',
        'user_id',
        'conversation_id',
        'cabin',
        'departure_iata',
        'arrival_iata',
        'flight_search_id',
        'device_type',
        'status',
        'loc',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'total_adults' => 'integer',
        'total_children' => 'integer',
        'total_babies' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->token)) {
                $order->token = (string) Str::ulid();
            }
            if (empty($order->tracking_code)) {
                do {
                    $code = 'VDP-' . strtoupper(Str::random(4));
                } while (static::where('tracking_code', $code)->exists());
                $order->tracking_code = $code;
            }
        });
    }

    public function flights(): HasMany
    {
        return $this->hasMany(OrderFlight::class);
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(OrderPassenger::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(OrderPayment::class)->latestOfMany();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    protected function passengersCount(): Attribute
    {
        return Attribute::get(fn () => $this->total_adults + $this->total_children + $this->total_babies);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeAwaitingPayment($query)
    {
        return $query->where('status', 'awaiting_payment');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function isAccessible(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    public function isMercosul(): bool
    {
        $mercosulIatas = config('mercosul_airports');

        return in_array(strtoupper($this->departure_iata), $mercosulIatas, true)
            && in_array(strtoupper($this->arrival_iata), $mercosulIatas, true);
    }
}
