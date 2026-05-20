@php
    $baggage = is_array($baggage ?? null) ? $baggage : [];
    $items = [
        'personal_item' => 'Item pessoal',
        'carry_on' => 'Mala de mao',
        'checked' => 'Mala despachada',
    ];
    $hasBaggage = false;
    foreach (array_keys($items) as $key) {
        if (isset($baggage[$key]) && is_array($baggage[$key])) {
            $hasBaggage = true;
            break;
        }
    }
    $labelFor = function (string $key, array $item) use ($items): string {
        $base = $items[$key] ?? 'Bagagem';
        $included = (bool) ($item['included'] ?? false);
        if (! $included) {
            return $base.' nao inclusa';
        }

        $quantity = (int) ($item['quantity'] ?? 1);
        $weight = trim((string) ($item['weight'] ?? ''));
        $label = $base.' inclusa';
        if ($quantity > 1) {
            $label .= ' ('.$quantity.' pecas';
            $label .= $weight !== '' ? ' de '.$weight.')' : ')';
        } elseif ($weight !== '') {
            $label .= ' ('.$weight.')';
        }

        return $label;
    };
@endphp

@if($hasBaggage)
    <div class="ml-auto flex items-center gap-1.5 shrink-0" aria-label="Bagagem">
        @foreach($items as $key => $defaultLabel)
            @php
                $item = isset($baggage[$key]) && is_array($baggage[$key]) ? $baggage[$key] : null;
                if (! $item) {
                    continue;
                }
                $included = (bool) ($item['included'] ?? false);
                $classes = $included ? 'text-emerald-700' : 'text-gray-300';
                $label = $labelFor($key, $item);
            @endphp
            <span class="{{ $classes }}" title="{{ $label }}" aria-label="{{ $label }}">
                @if($key === 'personal_item')
                    <svg class="w-4 h-4 sm:w-[18px] sm:h-[18px]" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M7 9V7a5 5 0 0 1 10 0v2" />
                        <path d="M6 9h12l1 10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L6 9Z" />
                        <path d="M9 14h6" />
                    </svg>
                @elseif($key === 'carry_on')
                    <svg class="w-4 h-4 sm:w-[18px] sm:h-[18px]" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M9 6V4h6v2" />
                        <rect x="7" y="6" width="10" height="14" rx="2" />
                        <path d="M10 20v2" />
                        <path d="M14 20v2" />
                        <path d="M10 10h4" />
                        <path d="M10 14h4" />
                    </svg>
                @else
                    <svg class="w-4 h-4 sm:w-[18px] sm:h-[18px]" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M8 7V5a3 3 0 0 1 3-3h2a3 3 0 0 1 3 3v2" />
                        <rect x="5" y="7" width="14" height="14" rx="2" />
                        <path d="M9 11h6" />
                        <path d="M9 15h6" />
                        <path d="M8 21v1" />
                        <path d="M16 21v1" />
                    </svg>
                @endif
            </span>
        @endforeach
    </div>
@endif
