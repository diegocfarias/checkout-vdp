<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowcaseRefreshLog extends Model
{
    protected $fillable = [
        'showcase_route_id',
        'status',
        'dates_searched',
        'cache_hits',
        'api_calls',
        'errors_count',
        'best_price',
        'best_date',
        'previous_price',
        'duration_seconds',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'best_price' => 'decimal:2',
        'previous_price' => 'decimal:2',
        'best_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'dates_searched' => 'integer',
        'cache_hits' => 'integer',
        'api_calls' => 'integer',
        'errors_count' => 'integer',
        'duration_seconds' => 'integer',
    ];

    public function showcaseRoute(): BelongsTo
    {
        return $this->belongsTo(ShowcaseRoute::class);
    }
}
