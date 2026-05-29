@php
    $direction = $direction ?? 'outbound';
    $label = $direction === 'inbound' ? 'Voo de volta' : 'Voo de ida';
    $rotation = $direction === 'inbound' ? ' rotate-180' : '';
    $classes = trim(($class ?? '') . ' inline-flex items-center justify-center text-blue-600');
@endphp

<span class="{{ $classes }}" aria-label="{{ $label }}" title="{{ $label }}">
    <svg class="w-4 h-4{{ $rotation }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/>
    </svg>
</span>
