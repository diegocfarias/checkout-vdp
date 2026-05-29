<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
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
                    $code = 'VDP-'.strtoupper(Str::random(4));
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

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class)->latest();
    }

    protected function passengersCount(): Attribute
    {
        return Attribute::get(fn () => $this->total_adults + $this->total_children + $this->total_babies);
    }

    public function isAwaitingCreditCardAnalysis(): bool
    {
        if ($this->status !== 'awaiting_payment') {
            return false;
        }

        $payment = $this->relationLoaded('latestPayment')
            ? $this->latestPayment
            : $this->latestPayment()->first();

        return $payment?->payment_method === 'credit_card';
    }

    public function displayStatusLabel(): string
    {
        if ($this->isAwaitingCreditCardAnalysis()) {
            return 'Pagamento em análise';
        }

        return match ($this->status) {
            'pending' => 'Pendente',
            'awaiting_payment' => 'Aguardando pagamento',
            'awaiting_emission' => 'Aguardando emissão',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            default => $this->status,
        };
    }

    public function displayStatusBadgeClasses(): string
    {
        if ($this->isAwaitingCreditCardAnalysis()) {
            return 'bg-amber-100 text-amber-700';
        }

        return match ($this->status) {
            'pending' => 'bg-amber-100 text-amber-700',
            'awaiting_payment' => 'bg-blue-100 text-blue-700',
            'awaiting_emission' => 'bg-purple-100 text-purple-700',
            'completed' => 'bg-emerald-100 text-emerald-700',
            'cancelled' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-600',
        };
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
            Log::warning('isMercosul: IATA codes vazios, assumindo Mercosul', [
                'order_id' => $this->id,
                'flight_search_id' => $this->flight_search_id,
            ]);

            return true;
        }

        $configPath = config_path('mercosul_airports.php');
        $mercosulIatas = file_exists($configPath) ? (require $configPath) : [];

        if (empty($mercosulIatas) || ! is_array($mercosulIatas)) {
            return true;
        }

        return in_array(strtoupper($dep), $mercosulIatas, true)
            && in_array(strtoupper($arr), $mercosulIatas, true);
    }
}
