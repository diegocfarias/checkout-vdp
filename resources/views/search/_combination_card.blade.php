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

<div class="combination-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
     data-airlines="{{ $airlinesStr }}"
     data-has-direct="{{ $hasDirect ? '1' : '0' }}"
     data-has-connection="{{ $hasConnection ? '1' : '0' }}"
     data-outbound-period="{{ $obPeriods }}"
     data-inbound-period="{{ $ibPeriods }}"
     data-price="{{ $totalPrice }}"
     data-same-cia="{{ $sameCia ? '1' : '0' }}"
     data-group="{{ $groupIdx }}">

    <div class="px-4 pt-4 pb-2 flex flex-wrap items-center gap-1.5">
        <span class="text-xs text-gray-500 font-medium">{{ implode(' / ', array_map('strtoupper', $airlines)) }}</span>
        @if($sameCia && $hasInbound)
            <span class="text-[10px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded font-medium">Mesma cia</span>
        @endif
        @if($hasDirect && !$hasConnection)
            <span class="text-[10px] bg-emerald-50 text-emerald-600 px-1.5 py-0.5 rounded font-medium">Direto</span>
        @endif
    </div>

    <div class="px-4 pb-4">
        {{-- Outbound --}}
        <p class="text-[10px] font-bold text-emerald-700 uppercase mb-1.5">Ida</p>
        <div class="space-y-1 mb-3">
            @foreach($obFlights as $fi => $flight)
                @php
                    $conns = $flight['connection'] ?? [];
                    $isDirect = !is_array($conns) || count($conns) <= 1;
                    $connCount = is_array($conns) ? max(0, count($conns) - 1) : 0;
                    $flightCia = strtoupper($flight['operator'] ?? '');
                    $hiddenOb = $fi >= $collapseAfter;
                @endphp
                <div class="{{ $hiddenOb ? 'collapsed-ob-' . $groupIdx . ' hidden' : '' }}">
                    <label class="flight-option flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors
                        {{ $fi === 0 ? 'border-emerald-400 bg-emerald-50' : 'border-gray-200 hover:border-gray-300' }}"
                        data-group="{{ $groupIdx }}" data-dir="ob">
                        <input type="radio" name="group_{{ $groupIdx }}_ob" value="{{ $fi }}"
                               class="sr-only ob-radio" data-group="{{ $groupIdx }}"
                               {{ $fi === 0 ? 'checked' : '' }}>
                        <div class="radio-dot w-3 h-3 rounded-full border-2 shrink-0 flex items-center justify-center
                            {{ $fi === 0 ? 'border-emerald-600' : 'border-gray-300' }}">
                            <div class="radio-dot-inner w-1.5 h-1.5 rounded-full {{ $fi === 0 ? 'bg-emerald-600' : '' }}"></div>
                        </div>
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <div class="shrink-0 w-12">
                                <span class="text-[10px] text-gray-500 font-medium block leading-tight">{{ $flightCia }}</span>
                                @if($flight['flight_number'] ?? null)
                                    <span class="text-[9px] text-gray-400 block leading-tight">{{ $flight['flight_number'] }}</span>
                                @endif
                            </div>
                            <span class="text-sm font-bold text-gray-800 shrink-0">{{ $flight['departure_time'] ?? '' }}</span>
                            <div class="flex-1 px-1 min-w-0">
                                <div class="border-t border-dashed border-gray-300 relative">
                                    <span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-white px-1 text-[9px] text-gray-400 whitespace-nowrap">{{ $flight['total_flight_duration'] ?? '' }}</span>
                                </div>
                                @if($isDirect)
                                    <p class="text-center text-[9px] text-emerald-600 mt-0.5">Direto</p>
                                @else
                                    <button type="button" class="conn-toggle-btn w-full text-center text-[9px] text-amber-600 mt-0.5 hover:text-amber-700 cursor-pointer"
                                            data-target="conn-ob-{{ $groupIdx }}-{{ $fi }}">
                                        {{ $connCount }} conexão ▾
                                    </button>
                                @endif
                            </div>
                            <span class="text-sm font-bold text-gray-800 shrink-0">{{ $flight['arrival_time'] ?? '' }}</span>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-[10px] text-gray-400">{{ $flight['departure_location'] ?? '' }} → {{ $flight['arrival_location'] ?? '' }}</p>
                        </div>
                    </label>
                    @if(!$isDirect)
                        <div id="conn-ob-{{ $groupIdx }}-{{ $fi }}" class="conn-details hidden ml-5 mr-2 mb-1 bg-gray-50 rounded-lg px-3 py-1 border border-gray-100">
                            @include('partials._connection_details', ['segments' => $conns, 'accentColor' => 'emerald', 'compact' => true])
                        </div>
                    @endif
                </div>
            @endforeach
            @if(count($obFlights) > $collapseAfter)
                <button type="button" class="toggle-more-btn w-full text-center text-[11px] text-emerald-600 font-medium py-1 hover:text-emerald-700"
                        data-target="collapsed-ob-{{ $groupIdx }}" data-count="{{ count($obFlights) - $collapseAfter }}">
                    + {{ count($obFlights) - $collapseAfter }} opções de ida
                </button>
            @endif
        </div>

        {{-- Inbound --}}
        @if($hasInbound)
        <p class="text-[10px] font-bold text-blue-700 uppercase mb-1.5">Volta</p>
        <div class="space-y-1 mb-3">
            @foreach($ibFlights as $fi => $flight)
                @php
                    $conns = $flight['connection'] ?? [];
                    $isDirect = !is_array($conns) || count($conns) <= 1;
                    $connCount = is_array($conns) ? max(0, count($conns) - 1) : 0;
                    $flightCia = strtoupper($flight['operator'] ?? '');
                    $hiddenIb = $fi >= $collapseAfter;
                @endphp
                <div class="{{ $hiddenIb ? 'collapsed-ib-' . $groupIdx . ' hidden' : '' }}">
                    <label class="flight-option flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors
                        {{ $fi === 0 ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}"
                        data-group="{{ $groupIdx }}" data-dir="ib">
                        <input type="radio" name="group_{{ $groupIdx }}_ib" value="{{ $fi }}"
                               class="sr-only ib-radio" data-group="{{ $groupIdx }}"
                               {{ $fi === 0 ? 'checked' : '' }}>
                        <div class="radio-dot w-3 h-3 rounded-full border-2 shrink-0 flex items-center justify-center
                            {{ $fi === 0 ? 'border-blue-600' : 'border-gray-300' }}">
                            <div class="radio-dot-inner w-1.5 h-1.5 rounded-full {{ $fi === 0 ? 'bg-blue-600' : '' }}"></div>
                        </div>
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <div class="shrink-0 w-12">
                                <span class="text-[10px] text-gray-500 font-medium block leading-tight">{{ $flightCia }}</span>
                                @if($flight['flight_number'] ?? null)
                                    <span class="text-[9px] text-gray-400 block leading-tight">{{ $flight['flight_number'] }}</span>
                                @endif
                            </div>
                            <span class="text-sm font-bold text-gray-800 shrink-0">{{ $flight['departure_time'] ?? '' }}</span>
                            <div class="flex-1 px-1 min-w-0">
                                <div class="border-t border-dashed border-gray-300 relative">
                                    <span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-white px-1 text-[9px] text-gray-400 whitespace-nowrap">{{ $flight['total_flight_duration'] ?? '' }}</span>
                                </div>
                                @if($isDirect)
                                    <p class="text-center text-[9px] text-emerald-600 mt-0.5">Direto</p>
                                @else
                                    <button type="button" class="conn-toggle-btn w-full text-center text-[9px] text-amber-600 mt-0.5 hover:text-amber-700 cursor-pointer"
                                            data-target="conn-ib-{{ $groupIdx }}-{{ $fi }}">
                                        {{ $connCount }} conexão ▾
                                    </button>
                                @endif
                            </div>
                            <span class="text-sm font-bold text-gray-800 shrink-0">{{ $flight['arrival_time'] ?? '' }}</span>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-[10px] text-gray-400">{{ $flight['departure_location'] ?? '' }} → {{ $flight['arrival_location'] ?? '' }}</p>
                        </div>
                    </label>
                    @if(!$isDirect)
                        <div id="conn-ib-{{ $groupIdx }}-{{ $fi }}" class="conn-details hidden ml-5 mr-2 mb-1 bg-gray-50 rounded-lg px-3 py-1 border border-gray-100">
                            @include('partials._connection_details', ['segments' => $conns, 'accentColor' => 'blue', 'compact' => true])
                        </div>
                    @endif
                </div>
            @endforeach
            @if(count($ibFlights) > $collapseAfter)
                <button type="button" class="toggle-more-btn w-full text-center text-[11px] text-blue-600 font-medium py-1 hover:text-blue-700"
                        data-target="collapsed-ib-{{ $groupIdx }}" data-count="{{ count($ibFlights) - $collapseAfter }}">
                    + {{ count($ibFlights) - $collapseAfter }} opções de volta
                </button>
            @endif
        </div>
        @endif
    </div>

    <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
        <div>
            <p class="text-xl font-bold text-gray-900">R$ {{ number_format($totalPrice, 2, ',', '.') }}</p>
            <p class="text-[10px] text-gray-400">{{ $hasInbound ? 'ida + volta' : 'por adulto' }}</p>
        </div>
        <form action="{{ route('search.select') }}" method="POST" class="group-form" data-group="{{ $groupIdx }}">
            @csrf
            <input type="hidden" name="search_id" value="{{ $searchId }}">
            <input type="hidden" name="outbound" class="selected-ob" value="{{ json_encode($obFlights[0] ?? [], JSON_UNESCAPED_UNICODE) }}">
            @if($hasInbound)
                <input type="hidden" name="inbound" class="selected-ib" value="{{ json_encode($ibFlights[0] ?? [], JSON_UNESCAPED_UNICODE) }}">
            @endif
            <button type="submit"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2.5 px-6 rounded-lg transition-colors">
                Selecionar
            </button>
        </form>
    </div>
</div>
