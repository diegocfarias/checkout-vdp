{{-- Recebe: $segments (array de connection), $accentColor ('emerald'|'blue'), $compact (bool, default false) --}}
@php
    $segments = $segments ?? [];
    $accent = $accentColor ?? 'emerald';
    $compact = $compact ?? false;
    $dotBg = $accent === 'blue' ? 'bg-blue-500' : 'bg-emerald-500';
    $lineBg = $accent === 'blue' ? 'bg-blue-200' : 'bg-emerald-200';
    $waitBg = $accent === 'blue' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600';
    $textSm = $compact ? 'text-xs' : 'text-sm';
    $textXs = $compact ? 'text-[11px]' : 'text-xs';
    $timeSz = $compact ? 'text-xs font-bold' : 'text-sm font-bold';
    $py = $compact ? 'py-1.5' : 'py-2';
@endphp

<div class="relative pl-5 {{ $compact ? 'mt-1' : 'mt-2' }}">
    <div class="absolute left-[7px] top-2 bottom-2 w-px {{ $lineBg }}"></div>

    @foreach($segments as $si => $seg)
        @php
            $depTime = $seg['DEPARTURE_TIME'] ?? '';
            $arrTime = $seg['ARRIVAL_TIME'] ?? '';
            $depIata = $seg['DEPARTURE_LOCATION'] ?? '';
            $arrIata = $seg['ARRIVAL_LOCATION'] ?? '';
            $flightNum = $seg['FLIGHT_NUMBER'] ?? '';
            $duration = $seg['FLIGHT_DURATION'] ?? '';
            $op = $seg['OP'] ?? '';
            $waitTime = $seg['TIME_WAITING'] ?? '';
            $isLast = $si === count($segments) - 1;
        @endphp

        <div class="relative {{ $py }}">
            <div class="absolute left-[-15px] top-{{ $compact ? '2.5' : '3' }} w-2 h-2 rounded-full {{ $dotBg }}"></div>
            <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="{{ $timeSz }} text-gray-800">{{ $depTime }}</span>
                        <span class="{{ $textSm }} text-gray-500">{{ $depIata }}</span>
                        <span class="{{ $textXs }} text-gray-300">→</span>
                        <span class="{{ $timeSz }} text-gray-800">{{ $arrTime }}</span>
                        <span class="{{ $textSm }} text-gray-500">{{ $arrIata }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-0.5">
                        @if($flightNum)
                            <span class="{{ $textXs }} text-gray-400">{{ $flightNum }}</span>
                        @endif
                        @if($duration)
                            <span class="{{ $textXs }} text-gray-400">· {{ $duration }}</span>
                        @endif
                        @if($op && $op !== ($seg['FLIGHT_NUMBER'] ?? ''))
                            <span class="{{ $textXs }} text-gray-400">· {{ $op }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if(!$isLast && $waitTime)
            <div class="relative {{ $compact ? 'py-0.5' : 'py-1' }}">
                <div class="absolute left-[-15px] top-1/2 -translate-y-1/2 w-2 h-2 rounded-full border-2 {{ $accent === 'blue' ? 'border-blue-300 bg-white' : 'border-amber-300 bg-white' }}"></div>
                <span class="inline-flex items-center gap-1 {{ $textXs }} {{ $waitBg }} px-2 py-0.5 rounded-full font-medium">
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Espera {{ $waitTime }} em {{ $arrIata }}
                </span>
            </div>
        @endif
    @endforeach
</div>
