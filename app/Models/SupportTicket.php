<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'order_id',
        'customer_id',
        'assigned_to',
        'subject',
        'status',
        'priority',
        'message',
        'first_response_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    const SUBJECTS = [
        'order_change' => 'Alterar dados do pedido',
        'cancellation' => 'Cancelamento',
        'refund' => 'Reembolso',
        'payment_issue' => 'Problema com pagamento',
        'emission_status' => 'Status da emissão',
        'general' => 'Dúvida geral',
        'other' => 'Outro',
    ];

    const STATUSES = [
        'open' => 'Aberto',
        'in_progress' => 'Em atendimento',
        'awaiting_customer' => 'Aguardando cliente',
        'awaiting_internal' => 'Aguardando interno',
        'resolved' => 'Resolvido',
        'closed' => 'Fechado',
    ];

    const PRIORITIES = [
        'low' => 'Baixa',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ticket) {
            if (empty($ticket->uuid)) {
                $ticket->uuid = (string) Str::uuid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    public function getSubjectLabelAttribute(): string
    {
        return self::SUBJECTS[$this->subject] ?? $this->subject;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, ['open', 'in_progress', 'awaiting_customer', 'awaiting_internal']);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress', 'awaiting_customer', 'awaiting_internal']);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeByAgent($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
