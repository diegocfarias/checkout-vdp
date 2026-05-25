<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingChangeLog extends Model
{
    protected $fillable = [
        'user_id',
        'restored_from_id',
        'action',
        'settings',
        'previous_settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'previous_settings' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restoredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'restored_from_id');
    }
}
