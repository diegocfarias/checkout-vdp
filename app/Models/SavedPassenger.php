<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPassenger extends Model
{
    protected $fillable = [
        'customer_id',
        'full_name',
        'nationality',
        'passport_number',
        'passport_expiry',
        'document',
        'birth_date',
        'email',
        'phone',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'passport_expiry' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
