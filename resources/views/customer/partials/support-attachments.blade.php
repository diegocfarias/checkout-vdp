@if($attachments->isNotEmpty())
    <div class="mt-3 space-y-2">
        @foreach($attachments as $attachment)
            <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-gray-700">{{ $attachment->original_name }}</p>
                    <p class="text-xs text-gray-400">{{ $attachment->formatted_size }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-3 text-xs font-semibold">
                    @if($attachment->is_previewable)
                        <a href="{{ route('customer.support.attachments.view', [$ticket, $attachment]) }}" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-700">
                            Visualizar
                        </a>
                    @endif
                    <a href="{{ route('customer.support.attachments.download', [$ticket, $attachment]) }}" class="text-gray-600 hover:text-gray-800">
                        Baixar
                    </a>
                </div>
            </div>
        @endforeach
    </div>
@endif
