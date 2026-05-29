@php
    $direction = $direction ?? 'outbound';
    $label = $direction === 'inbound' ? 'Voo de volta - avião pousando' : 'Voo de ida - avião decolando';
    $classes = trim(($class ?? '') . ' inline-flex items-center justify-center text-blue-600');
@endphp

<span class="{{ $classes }}" aria-label="{{ $label }}" title="{{ $label }}">
    @if($direction === 'inbound')
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 17h20"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 14l6.2 2.1c.9.3 1.8.3 2.7 0L20 14"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12L5 7"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 15l-3-9"/>
        </svg>
    @else
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 17h20"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 15l6.4-2.4c.8-.3 1.8-.2 2.5.3L20 17"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14l-3-4"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13l2-8"/>
        </svg>
    @endif
</span>
