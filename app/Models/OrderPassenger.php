<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPassenger extends Model
{
    protected $fillable = [
        'order_id',
        'full_name',
        'document',
        'birth_date',
        'email',
        'phone',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function customerOrders(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->document) {
                return collect();
            }

            return Order::whereHas('passengers', fn ($q) => $q->where('document', $this->document))
                ->orderByDesc('created_at')
                ->get();
        });
    }
}
