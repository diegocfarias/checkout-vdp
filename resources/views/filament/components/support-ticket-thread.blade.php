@php
    $ticket = $getRecord();
    $ticket->loadMissing(['messages.user', 'messages.customer']);
    $messages = $ticket->messages->sortBy('created_at');
@endphp

<div style="max-width: 100%;">
    @if($messages->isEmpty())
        <div style="text-align: center; padding: 24px; color: #9ca3af; font-size: 14px;">
            Nenhuma resposta ainda.
        </div>
    @else
        <div style="display: flex; flex-direction: column; gap: 16px;">
            @foreach($messages as $msg)
                @php
                    $isAgent = $msg->user_id !== null;
                    $isInternal = $msg->is_internal_note;
                    $bgColor = $isInternal ? '#fef9c3' : ($isAgent ? '#eff6ff' : '#f9fafb');
                    $borderColor = $isInternal ? '#fde047' : ($isAgent ? '#93c5fd' : '#e5e7eb');
                    $label = $isInternal ? '🔒 Nota interna' : ($isAgent ? '👤 ' . e($msg->sender_name) : '🙋 ' . e($msg->sender_name));
                @endphp
                <div style="background: {{ $bgColor }}; border: 1px solid {{ $borderColor }}; border-radius: 8px; padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: 600; font-size: 13px; color: #374151;">
                            {{ $label }}
                        </span>
                        <span style="font-size: 12px; color: #6b7280;">
                            {{ $msg->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                        </span>
                    </div>
                    <div style="font-size: 14px; line-height: 1.6; color: #1f2937; white-space: pre-wrap;">{{ $msg->message }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
