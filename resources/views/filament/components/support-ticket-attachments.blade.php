@php
    $ticket = $getRecord();
    $ticket->loadMissing('initialAttachments');
    $attachments = $ticket->initialAttachments->sortBy('created_at');
@endphp

@if($attachments->isNotEmpty())
    <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 12px;">
        @foreach($attachments as $attachment)
            <div style="display: flex; justify-content: space-between; gap: 12px; align-items: center; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px;">
                <div style="min-width: 0;">
                    <div style="font-size: 14px; font-weight: 600; color: #374151; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        {{ $attachment->original_name }}
                    </div>
                    <div style="font-size: 12px; color: #6b7280;">{{ $attachment->formatted_size }}</div>
                </div>
                <div style="display: flex; gap: 12px; font-size: 13px; font-weight: 600; flex-shrink: 0;">
                    @if($attachment->is_previewable)
                        <a href="{{ route('admin.support.attachments.view', $attachment) }}" target="_blank" rel="noopener" style="color: #2563eb;">Visualizar</a>
                    @endif
                    <a href="{{ route('admin.support.attachments.download', $attachment) }}" style="color: #374151;">Baixar</a>
                </div>
            </div>
        @endforeach
    </div>
@endif
