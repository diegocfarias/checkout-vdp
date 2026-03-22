<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    protected $fillable = [
        'order_id',
        'status',
        'description',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pedido criado',
            'awaiting_payment' => 'Aguardando pagamento',
            'awaiting_emission' => 'Pagamento confirmado, aguardando emissão',
            'completed' => 'Passagens emitidas',
            'cancelled' => 'Pedido cancelado',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
