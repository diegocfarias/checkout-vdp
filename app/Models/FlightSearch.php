<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FlightSearch extends Model
{
    use HasUuids;

    protected $fillable = [
        'departure_iata',
        'arrival_iata',
        'outbound_date',
        'inbound_date',
        'trip_type',
        'cabin',
        'adults',
        'children',
        'infants',
        'ip_address',
        'user_agent',
        'results_count',
    ];

    protected $casts = [
        'outbound_date' => 'date',
        'inbound_date' => 'date',
        'adults' => 'integer',
        'children' => 'integer',
        'infants' => 'integer',
        'results_count' => 'integer',
    ];
}
