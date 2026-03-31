<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'customer_id',
        'coupon_id',
        'referral_id',
        'discount_amount',
        'wallet_amount_used',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function flightSearch(): BelongsTo
    {
        return $this->belongsTo(FlightSearch::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function emission(): HasOne
    {
        return $this->hasOne(OrderEmission::class);
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
        $dep = $this->departure_iata;
        $arr = $this->arrival_iata;

        if (empty($dep) || empty($arr)) {
            $fs = $this->relationLoaded('flightSearch') ? $this->flightSearch : $this->flightSearch()->first();
            if ($fs) {
                $dep = $dep ?: $fs->departure_iata;
                $arr = $arr ?: $fs->arrival_iata;
            }
        }

        if (empty($dep) || empty($arr)) {
            return false;
        }

        $mercosulIatas = config('mercosul_airports');

        return in_array(strtoupper($dep), $mercosulIatas, true)
            && in_array(strtoupper($arr), $mercosulIatas, true);
    }
}
