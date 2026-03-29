@php
    $gradients = [
        'from-blue-500 to-teal-600',
        'from-blue-500 to-indigo-600',
        'from-purple-500 to-violet-600',
        'from-amber-500 to-orange-600',
        'from-rose-500 to-pink-600',
        'from-cyan-500 to-blue-600',
        'from-green-500 to-teal-600',
        'from-indigo-500 to-purple-600',
        'from-teal-500 to-cyan-600',
    ];
    $gradient = $gradients[$loop->index % count($gradients)] ?? $gradients[0];

    $hasImage = !empty($route->image_url);
    $tripLabel = $route->trip_type === 'roundtrip' ? 'Ida e volta' : 'Somente ida';
    $airline = strtoupper($route->cached_airline ?? '');

    $searchUrl = route('search.results', [
        'departure' => strtoupper($route->departure_iata),
        'arrival' => strtoupper($route->arrival_iata),
        'outbound_date' => $route->cached_date?->format('Y-m-d'),
        'inbound_date' => $route->cached_return_date?->format('Y-m-d'),
        'trip_type' => $route->trip_type,
        'cabin' => $route->cabin,
        'adults' => 1,
        'children' => 0,
        'infants' => 0,
    ]);

    $hasPixDiscount = isset($pixEnabled) && $pixEnabled && isset($pixDiscount) && $pixDiscount > 0;
    $pixPrice = $hasPixDiscount ? round((float)$route->cached_price * (1 - $pixDiscount / 100), 2) : null;
    $monthLabel = $route->cached_date ? $route->cached_date->translatedFormat('M/Y') : '';
@endphp

<a href="{{ $searchUrl }}" class="group block rounded-xl overflow-hidden border border-gray-200 hover:border-blue-300 hover:shadow-lg transition-all duration-300 h-full bg-white">
    {{-- Imagem --}}
    <div class="relative h-36 sm:h-32 lg:h-40 overflow-hidden">
        @if($hasImage)
            <img src="{{ $route->image_url }}" alt="{{ $route->arrival_city }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
        @else
            <div class="w-full h-full bg-gradient-to-br {{ $gradient }}"></div>
        @endif

        @if($airline)
        <div class="absolute top-2 left-2">
            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-white/95 backdrop-blur-sm text-[11px] font-bold text-gray-700 shadow-sm">
                {{ $airline }}
            </span>
        </div>
        @endif
    </div>

    {{-- Info --}}
    <div class="p-4">
        <h3 class="text-base font-bold text-gray-900 leading-snug mb-1">{{ $route->arrival_city }}</h3>

        <p class="text-xs text-gray-400 mb-3">
            {{ strtoupper($route->departure_iata) }} → {{ strtoupper($route->arrival_iata) }}
            <span class="mx-1">·</span>
            {{ $tripLabel }}
            @if($monthLabel)
                <span class="mx-1">·</span>
                {{ $monthLabel }}
            @endif
        </p>

        <div class="flex items-end justify-between">
            <div>
                <p class="text-[11px] text-gray-400 uppercase tracking-wide">a partir de</p>
                @if($hasPixDiscount)
                    <p class="text-xs text-gray-400 line-through">{{ $route->formattedPrice() }}</p>
                    <p class="text-lg font-bold text-emerald-600 leading-tight">R$ {{ number_format($pixPrice, 2, ',', '.') }}</p>
                    <p class="text-[11px] text-emerald-500 font-medium">{{ number_format($pixDiscount, 0) }}% off no PIX</p>
                @else
                    <p class="text-lg font-bold text-gray-900 leading-tight">{{ $route->formattedPrice() }}</p>
                @endif
            </div>
            <span class="text-xs text-blue-600 font-medium group-hover:underline flex items-center gap-1">
                Ver voos
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
        </div>
    </div>
</a>
