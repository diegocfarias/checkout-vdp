<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFlight extends Model
{
    protected $fillable = [
        'order_id',
        'direction',
        'cia',
        'operator',
        'flight_number',
        'departure_time',
        'arrival_time',
        'departure_location',
        'arrival_location',
        'departure_label',
        'arrival_label',
        'boarding_tax',
        'class_service',
        'price_money',
        'price_miles',
        'price_miles_vip',
        'total_flight_duration',
        'unique_id',
        'loc',
        'connection',
        'miles_price',
        'money_price',
        'tax',
        'provider',
        'pricing_type',
    ];

    protected $casts = [
        'connection' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
