@php
    $obFlights = $group['outbound_flights'];
    $ibFlights = $group['inbound_flights'] ?? [];
    $totalPrice = $group['total_price'];
    $sameCia = $group['same_cia'] ?? false;
    $airlines = $group['airlines'] ?? [];
    $airlinesStr = implode(',', array_map('strtolower', $airlines));
    $obPeriods = implode(',', $group['outbound_periods'] ?? []);
    $ibPeriods = implode(',', $group['inbound_periods'] ?? []);
    $hasInbound = count($ibFlights) > 0;
    $groupIdx = $groupIdx ?? 0;
    $collapseAfter = 2;

    $hasDirect = false;
    $hasConnection = false;
    foreach ($obFlights as $f) {
        $c = $f['connection'] ?? [];
        if (!is_array($c) || count($c) <= 1) { $hasDirect = true; } else { $hasConnection = true; }
    }
    foreach ($ibFlights as $f) {
        $c = $f['connection'] ?? [];
        if (!is_array($c) || count($c) <= 1) { $hasDirect = true; } else { $hasConnection = true; }
    }
@endphp

<div class="combination-card bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200 animate-fadeIn"
     data-airlines="{{ $airlinesStr }}"
     data-has-direct="{{ $hasDirect ? '1' : '0' }}"
     data-has-connection="{{ $hasConnection ? '1' : '0' }}"
     data-outbound-period="{{ $obPeriods }}"
     data-inbound-period="{{ $ibPeriods }}"
     data-price="{{ $totalPrice }}"
     data-same-cia="{{ $sameCia ? '1' : '0' }}"
     data-group="{{ $groupIdx }}">

    {{-- Preco + CTA (sticky no topo) --}}
    <div class="sticky top-14 z-10 flex items-center justify-between px-5 py-4 bg-white border-b border-gray-100 rounded-t-xl">
        <div>
            @if(($pixEnabled ?? false) && ($pixDiscount ?? 0) > 0)
                @php $pixPrice = round($totalPrice * (1 - ($pixDiscount / 100)), 2); @endphp
                <p class="text-sm text-gray-400 line-through">R$ {{ number_format($totalPrice, 2, ',', '.') }}</p>
                <div class="flex items-center gap-2">
                    <p class="text-2xl font-bold text-emerald-600">R$ {{ number_format($pixPrice, 2, ',', '.') }}</p>
                    <span class="text-xs font-semibold bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full whitespace-nowrap">PIX</span>
                </div>
            @else
                <p class="text-2xl font-bold text-gray-900">R$ {{ number_format($totalPrice, 2, ',', '.') }}</p>
            @endif
            <p class="text-xs text-gray-400 mt-0.5">{{ $hasInbound ? 'ida + volta' : 'por adulto' }}</p>
        </div>
        <form action="{{ route('search.select') }}" method="POST" class="group-form" data-group="{{ $groupIdx }}">
            @csrf
            <input type="hidden" name="search_id" value="{{ $searchId }}">
            <input type="hidden" name="outbound" class="selected-ob" value="{{ json_encode($obFlights[0] ?? [], JSON_UNESCAPED_UNICODE) }}">
            @if($hasInbound)
                <input type="hidden" name="inbound" class="selected-ib" value="{{ json_encode($ibFlights[0] ?? [], JSON_UNESCAPED_UNICODE) }}">
            @endif
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-xl transition-colors text-sm">
                Selecionar
            </button>
        </form>
    </div>

    {{-- Badges --}}
    <div class="px-5 pt-4 pb-2 flex flex-wrap items-center gap-2">
        <span class="text-sm text-gray-600 font-semibold">{{ implode(' / ', array_map('strtoupper', $airlines)) }}</span>
        @if($sameCia && $hasInbound)
            <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-medium">Mesma cia</span>
        @endif
        @if($hasDirect && !$hasConnection)
            <span class="text-xs bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full font-medium">Direto</span>
        @endif
    </div>

    <div class="px-5 pb-5">
        {{-- Outbound --}}
        <p class="text-xs font-bold text-blue-700 uppercase tracking-wide mb-2">Ida</p>
        <div class="space-y-2 mb-4">
            @foreach($obFlights as $fi => $flight)
                @php
                    $conns = $flight['connection'] ?? [];
                    $isDirect = !is_array($conns) || count($conns) <= 1;
                    $connCount = is_array($conns) ? max(0, count($conns) - 1) : 0;
                    $flightCia = strtoupper($flight['operator'] ?? '');
                    $hiddenOb = $fi >= $collapseAfter;
                @endphp
                <div class="{{ $hiddenOb ? 'collapsed-ob-' . $groupIdx . ' hidden' : '' }}">
                    <label class="flight-option block p-4 rounded-xl border cursor-pointer transition-all
                        {{ $fi === 0 ? 'border-blue-400 bg-blue-50/60 shadow-sm' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                        data-group="{{ $groupIdx }}" data-dir="ob">
                        <input type="radio" name="group_{{ $groupIdx }}_ob" value="{{ $fi }}"
                               class="sr-only ob-radio" data-group="{{ $groupIdx }}"
                               {{ $fi === 0 ? 'checked' : '' }}>
                        <div class="flex items-start gap-3">
                            <div class="radio-dot w-5 h-5 rounded-full border-2 shrink-0 flex items-center justify-center mt-1
                                {{ $fi === 0 ? 'border-blue-600' : 'border-gray-300' }}">
                                <div class="radio-dot-inner w-2.5 h-2.5 rounded-full {{ $fi === 0 ? 'bg-blue-600' : '' }}"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-2 text-xs text-gray-500">
                                    <span class="font-semibold">{{ $flightCia }}</span>
                                    @if($flight['flight_number'] ?? null)
                                        <span class="text-gray-400">{{ $flight['flight_number'] }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center">
                                    <div class="text-center shrink-0">
                                        <p class="text-lg font-bold text-gray-800 leading-tight">{{ $flight['departure_time'] ?? '' }}</p>
                                        <p class="text-xs font-medium text-gray-500">{{ $flight['departure_location'] ?? '' }}</p>
                                    </div>
                                    <div class="flex-1 mx-3 sm:mx-4 flex flex-col items-center min-w-[80px]">
                                        <span class="text-xs text-gray-400 font-medium mb-1">{{ $flight['total_flight_duration'] ?? '' }}</span>
                                        <div class="w-full border-t-2 border-dashed border-gray-300"></div>
                                        @if($isDirect)
                                            <span class="text-xs text-emerald-600 font-semibold mt-1">Direto</span>
                                        @else
                                            <button type="button" class="conn-toggle-btn text-xs text-amber-600 font-semibold mt-1 hover:text-amber-700 cursor-pointer"
                                                    data-target="conn-ob-{{ $groupIdx }}-{{ $fi }}">
                                                {{ $connCount }} conexão ▾
                                            </button>
                                        @endif
                                    </div>
                                    <div class="text-center shrink-0">
                                        <p class="text-lg font-bold text-gray-800 leading-tight">{{ $flight['arrival_time'] ?? '' }}</p>
                                        <p class="text-xs font-medium text-gray-500">{{ $flight['arrival_location'] ?? '' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </label>
                    @if(!$isDirect)
                        <div id="conn-ob-{{ $groupIdx }}-{{ $fi }}" class="conn-details hidden ml-8 mr-2 mt-1 mb-1 bg-gray-50 rounded-lg px-4 py-2 border border-gray-100">
                            @include('partials._connection_details', ['segments' => $conns, 'accentColor' => 'blue', 'compact' => true])
                        </div>
                    @endif
                </div>
            @endforeach
            @if(count($obFlights) > $collapseAfter)
                <button type="button" class="toggle-more-btn w-full text-center text-sm text-blue-600 font-medium py-2 hover:text-blue-700"
                        data-target="collapsed-ob-{{ $groupIdx }}" data-count="{{ count($obFlights) - $collapseAfter }}">
                    + {{ count($obFlights) - $collapseAfter }} opções de ida
                </button>
            @endif
        </div>

        {{-- Inbound --}}
        @if($hasInbound)
        <div class="border-t border-gray-100 pt-4">
            <p class="text-xs font-bold text-blue-700 uppercase tracking-wide mb-2">Volta</p>
            <div class="space-y-2 mb-2">
                @foreach($ibFlights as $fi => $flight)
                    @php
                        $conns = $flight['connection'] ?? [];
                        $isDirect = !is_array($conns) || count($conns) <= 1;
                        $connCount = is_array($conns) ? max(0, count($conns) - 1) : 0;
                        $flightCia = strtoupper($flight['operator'] ?? '');
                        $hiddenIb = $fi >= $collapseAfter;
                    @endphp
                    <div class="{{ $hiddenIb ? 'collapsed-ib-' . $groupIdx . ' hidden' : '' }}">
                        <label class="flight-option block p-4 rounded-xl border cursor-pointer transition-all
                            {{ $fi === 0 ? 'border-blue-400 bg-blue-50/60 shadow-sm' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                            data-group="{{ $groupIdx }}" data-dir="ib">
                            <input type="radio" name="group_{{ $groupIdx }}_ib" value="{{ $fi }}"
                                   class="sr-only ib-radio" data-group="{{ $groupIdx }}"
                                   {{ $fi === 0 ? 'checked' : '' }}>
                            <div class="flex items-start gap-3">
                                <div class="radio-dot w-5 h-5 rounded-full border-2 shrink-0 flex items-center justify-center mt-1
                                    {{ $fi === 0 ? 'border-blue-600' : 'border-gray-300' }}">
                                    <div class="radio-dot-inner w-2.5 h-2.5 rounded-full {{ $fi === 0 ? 'bg-blue-600' : '' }}"></div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-2 text-xs text-gray-500">
                                        <span class="font-semibold">{{ $flightCia }}</span>
                                        @if($flight['flight_number'] ?? null)
                                            <span class="text-gray-400">{{ $flight['flight_number'] }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center">
                                        <div class="text-center shrink-0">
                                            <p class="text-lg font-bold text-gray-800 leading-tight">{{ $flight['departure_time'] ?? '' }}</p>
                                            <p class="text-xs font-medium text-gray-500">{{ $flight['departure_location'] ?? '' }}</p>
                                        </div>
                                        <div class="flex-1 mx-3 sm:mx-4 flex flex-col items-center min-w-[80px]">
                                            <span class="text-xs text-gray-400 font-medium mb-1">{{ $flight['total_flight_duration'] ?? '' }}</span>
                                            <div class="w-full border-t-2 border-dashed border-gray-300"></div>
                                            @if($isDirect)
                                                <span class="text-xs text-emerald-600 font-semibold mt-1">Direto</span>
                                            @else
                                                <button type="button" class="conn-toggle-btn text-xs text-amber-600 font-semibold mt-1 hover:text-amber-700 cursor-pointer"
                                                        data-target="conn-ib-{{ $groupIdx }}-{{ $fi }}">
                                                    {{ $connCount }} conexão ▾
                                                </button>
                                            @endif
                                        </div>
                                        <div class="text-center shrink-0">
                                            <p class="text-lg font-bold text-gray-800 leading-tight">{{ $flight['arrival_time'] ?? '' }}</p>
                                            <p class="text-xs font-medium text-gray-500">{{ $flight['arrival_location'] ?? '' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </label>
                        @if(!$isDirect)
                            <div id="conn-ib-{{ $groupIdx }}-{{ $fi }}" class="conn-details hidden ml-8 mr-2 mt-1 mb-1 bg-gray-50 rounded-lg px-4 py-2 border border-gray-100">
                                @include('partials._connection_details', ['segments' => $conns, 'accentColor' => 'blue', 'compact' => true])
                            </div>
                        @endif
                    </div>
                @endforeach
                @if(count($ibFlights) > $collapseAfter)
                    <button type="button" class="toggle-more-btn w-full text-center text-sm text-blue-600 font-medium py-2 hover:text-blue-700"
                            data-target="collapsed-ib-{{ $groupIdx }}" data-count="{{ count($ibFlights) - $collapseAfter }}">
                        + {{ count($ibFlights) - $collapseAfter }} opções de volta
                    </button>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
