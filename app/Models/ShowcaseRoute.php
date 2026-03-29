<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShowcaseRoute extends Model
{
    protected $fillable = [
        'departure_iata',
        'departure_city',
        'arrival_iata',
        'arrival_city',
        'trip_type',
        'cabin',
        'search_window_days',
        'return_stay_days',
        'sample_dates_count',
        'search_date_from',
        'search_date_to',
        'is_active',
        'sort_order',
        'image_url',
        'image_credit',
        'image_search_query',
        'image_zoom',
        'cached_price',
        'cached_date',
        'cached_return_date',
        'cached_airline',
        'cached_flight_data',
        'last_refreshed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cached_price' => 'decimal:2',
        'cached_date' => 'date',
        'cached_return_date' => 'date',
        'cached_flight_data' => 'json',
        'last_refreshed_at' => 'datetime',
        'search_window_days' => 'integer',
        'return_stay_days' => 'integer',
        'sample_dates_count' => 'integer',
        'sort_order' => 'integer',
        'search_date_from' => 'date',
        'search_date_to' => 'date',
        'image_zoom' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereNotNull('cached_price');
    }

    /**
     * Gera datas amostrais distribuidas uniformemente no intervalo.
     * Usa search_date_from/search_date_to se definidos, senao fallback para search_window_days.
     */
    public function sampleDates(): array
    {
        if ($this->search_date_from && $this->search_date_to) {
            $start = $this->search_date_from->copy();
            $end = $this->search_date_to->copy();
        } else {
            $start = Carbon::today()->addDays(3);
            $end = Carbon::today()->addDays($this->search_window_days ?? 30);
        }

        $today = Carbon::today();
        if ($start->lt($today)) {
            $start = $today->copy()->addDay();
        }

        if ($end->lte($start)) {
            return [$start->format('Y-m-d')];
        }

        $count = max(1, $this->sample_dates_count ?? 8);
        $totalDays = $start->diffInDays($end);

        if ($totalDays <= 0) {
            return [$start->format('Y-m-d')];
        }

        if ($count === 1) {
            return [$start->format('Y-m-d')];
        }

        $step = max(1, (int) floor($totalDays / ($count - 1)));
        $dates = [];

        for ($i = 0; $i < $count; $i++) {
            $date = $start->copy()->addDays($i * $step);
            if ($date->gt($end)) {
                break;
            }
            $dates[] = $date->format('Y-m-d');
        }

        if (! in_array($end->format('Y-m-d'), $dates)) {
            $dates[] = $end->format('Y-m-d');
        }

        return $dates;
    }

    public function formattedPrice(): string
    {
        if (! $this->cached_price) {
            return '-';
        }

        return 'R$ ' . number_format((float) $this->cached_price, 2, ',', '.');
    }

    public function routeLabel(): string
    {
        return strtoupper($this->departure_iata) . ' → ' . strtoupper($this->arrival_iata);
    }

    public function refreshLogs(): HasMany
    {
        return $this->hasMany(ShowcaseRefreshLog::class);
    }
}
