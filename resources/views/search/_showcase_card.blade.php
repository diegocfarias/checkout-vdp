@php
    $gradients = [
        'from-emerald-600 to-teal-700',
        'from-blue-600 to-indigo-700',
        'from-purple-600 to-violet-700',
        'from-amber-500 to-orange-600',
        'from-rose-500 to-pink-600',
        'from-cyan-600 to-blue-700',
        'from-green-600 to-emerald-700',
        'from-indigo-500 to-purple-600',
        'from-teal-500 to-emerald-600',
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
    $dateLabel = $route->cached_date ? $route->cached_date->translatedFormat('d M') : '';
    $monthLabel = $route->cached_date ? $route->cached_date->translatedFormat('M/Y') : '';
@endphp

<a href="{{ $searchUrl }}" class="group block rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 h-full">
    <div class="relative h-56 sm:h-52 lg:h-56">
        @if($hasImage)
            <img src="{{ $route->image_url }}" alt="{{ $route->arrival_city }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
        @else
            <div class="absolute inset-0 bg-gradient-to-br {{ $gradient }}">
                <div class="absolute inset-0 opacity-10">
                    <svg class="absolute right-4 top-4 w-32 h-32 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="0.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </div>
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
        @endif

        {{-- Badge Cia aérea --}}
        @if($airline)
        <div class="absolute top-3 left-3">
            <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-white/90 backdrop-blur-sm text-xs font-semibold text-gray-800">
                {{ $airline }}
            </span>
        </div>
        @endif

        {{-- Badge tipo viagem --}}
        <div class="absolute top-3 right-3">
            <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-black/40 backdrop-blur-sm text-xs font-medium text-white">
                {{ $tripLabel }}
            </span>
        </div>

        {{-- Info sobre a imagem --}}
        <div class="absolute bottom-0 left-0 right-0 p-4 sm:p-5">
            <div class="flex items-end justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-white/70 text-xs font-medium uppercase tracking-wider mb-0.5">
                        {{ strtoupper($route->departure_iata) }}
                        <svg class="inline w-3 h-3 mx-0.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        {{ strtoupper($route->arrival_iata) }}
                    </p>
                    <h3 class="text-white text-xl sm:text-2xl font-bold truncate">{{ $route->arrival_city }}</h3>
                    @if($monthLabel)
                    <p class="text-white/60 text-xs mt-0.5">em {{ $monthLabel }}</p>
                    @endif
                </div>

                <div class="text-right shrink-0">
                    <p class="text-white/60 text-[10px] uppercase tracking-wider">a partir de</p>
                    @if($hasPixDiscount)
                        <p class="text-white/50 text-xs line-through">{{ $route->formattedPrice() }}</p>
                        <p class="text-white text-xl font-bold">R$ {{ number_format($pixPrice, 2, ',', '.') }}</p>
                        <p class="text-emerald-300 text-[10px] font-medium">{{ number_format($pixDiscount, 0) }}% off no PIX</p>
                    @else
                        <p class="text-white text-xl font-bold">{{ $route->formattedPrice() }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</a>
