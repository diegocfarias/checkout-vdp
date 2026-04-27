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

    $pixOn = ($pixEnabled ?? false) && ($pixDiscount ?? 0) > 0;
    $pixPrice = $pixOn ? round($totalPrice * (1 - ($pixDiscount / 100)), 2) : $totalPrice;
    $pixSavings = $pixOn ? round($totalPrice - $pixPrice, 2) : 0;

    $obDateFormatted = isset($params['outbound_date'])
        ? \Carbon\Carbon::parse($params['outbound_date'])->translatedFormat('D, d/m/Y')
        : '';
    $ibDateFormatted = !empty($params['inbound_date'])
        ? \Carbon\Carbon::parse($params['inbound_date'])->translatedFormat('D, d/m/Y')
        : '';

    $totalPax = ($params['adults'] ?? 1) + ($params['children'] ?? 0);
    $totalAllPax = round($totalPrice * $totalPax, 2);
    $pixAllPax = $pixOn ? round($pixPrice * $totalPax, 2) : $totalAllPax;

    $parseTax = function($val) {
        $val = trim((string)($val ?? '0'));
        if ($val === '') return 0;
        if (str_contains($val, ',')) return (float) str_replace(',', '.', str_replace('.', '', $val));
        if (preg_match('/\.\d{3}$/', $val)) return (float) str_replace('.', '', $val);
        return (float) $val;
    };
    $obTax = $parseTax($obFlights[0]['boarding_tax'] ?? '0');
    $ibTax = $hasInbound ? $parseTax($ibFlights[0]['boarding_tax'] ?? '0') : 0;
    $totalTax = round($obTax + $ibTax, 2);
    $basePrice = round($totalPrice - $totalTax, 2);

    $displayCia = function($operator, $flightNumber) {
        $op = strtoupper(trim((string) $operator));
        if ($op !== 'PATRIA') return $op;

        $fn = strtoupper(trim((string) $flightNumber));
        if (str_starts_with($fn, 'G3')) return 'GOL';
        if (str_starts_with($fn, 'AD')) return 'AZUL';
        if (str_starts_with($fn, 'LA') || str_starts_with($fn, 'JJ')) return 'LATAM';
        if (preg_match('/^[A-Z0-9]{2}/', $fn, $m)) return $m[0];

        return 'CIA';
    };
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

    <div class="flex flex-col lg:flex-row">
        {{-- Coluna esquerda: voos --}}
        <div class="flex-1 min-w-0">
            {{-- Preço mobile --}}
            <div class="lg:hidden sticky top-16 z-10 flex items-center justify-between px-4 py-3 bg-white border-b border-gray-100 rounded-t-xl shadow-sm">
                <div class="whitespace-nowrap">
                    @if($pixOn)
                        <p class="text-[11px] text-gray-400 line-through">R$ {{ number_format($totalPrice, 2, ',', '.') }}</p>
                        <div class="flex items-center gap-1.5">
                            <p class="text-lg font-bold text-emerald-600">R$ {{ number_format($pixPrice, 2, ',', '.') }}</p>
                            <span class="text-[10px] font-semibold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full">No Pix</span>
                        </div>
                    @else
                        <p class="text-lg font-bold text-gray-900">R$ {{ number_format($totalPrice, 2, ',', '.') }}</p>
                    @endif
                    <p class="text-[11px] text-gray-400">Por adulto{{ $hasInbound ? ', ida e volta' : '' }}</p>
                    @if($totalPax > 1)
                        <p class="text-[11px] text-gray-500 font-medium">Total ({{ $totalPax }}x): R$ {{ number_format($pixOn ? $pixAllPax : $totalAllPax, 2, ',', '.') }}</p>
                    @endif
                </div>
                <form action="{{ route('search.select') }}" method="POST" class="group-form" data-group="{{ $groupIdx }}">
                    @csrf
                    <input type="hidden" name="search_id" value="{{ $searchId }}">
                    <input type="hidden" name="outbound" class="selected-ob" value="{{ json_encode($obFlights[0] ?? [], JSON_UNESCAPED_UNICODE) }}">
                    @if($hasInbound)
                        <input type="hidden" name="inbound" class="selected-ib" value="{{ json_encode($ibFlights[0] ?? [], JSON_UNESCAPED_UNICODE) }}">
                    @endif
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-xl transition-colors text-sm">
                        Comprar
                    </button>
                </form>
            </div>

            <div class="px-5 pb-5">
                {{-- Outbound --}}
                <div class="flex items-center justify-between pt-4 pb-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
                        <p class="text-sm font-bold text-gray-800">Ida</p>
                    </div>
                    @if($obDateFormatted)
                        <span class="text-xs text-gray-500 font-medium">{{ $obDateFormatted }}</span>
                    @endif
                </div>

                <div class="space-y-2 mb-4">
                    @foreach($obFlights as $fi => $flight)
                        @php
                            $conns = $flight['connection'] ?? [];
                            $isDirect = !is_array($conns) || count($conns) <= 1;
                            $connCount = is_array($conns) ? max(0, count($conns) - 1) : 0;
                            $flightCia = $displayCia($flight['operator'] ?? '', $flight['flight_number'] ?? '');
                            $hiddenOb = $fi >= $collapseAfter;
                        @endphp
                        <div class="{{ $hiddenOb ? 'collapsed-ob-' . $groupIdx . ' hidden' : '' }}">
                            <label class="flight-option block p-3 sm:p-4 rounded-xl border cursor-pointer transition-all
                                {{ $fi === 0 ? 'border-blue-400 bg-blue-50/60 shadow-sm' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                                data-group="{{ $groupIdx }}" data-dir="ob">
                                <input type="radio" name="group_{{ $groupIdx }}_ob" value="{{ $fi }}"
                                       class="sr-only ob-radio" data-group="{{ $groupIdx }}"
                                       {{ $fi === 0 ? 'checked' : '' }}>
                                <div class="flex items-start gap-2 sm:gap-3">
                                    <div class="radio-dot w-5 h-5 rounded-full border-2 shrink-0 flex items-center justify-center mt-1
                                        {{ $fi === 0 ? 'border-blue-600' : 'border-gray-300' }}">
                                        <div class="radio-dot-inner w-2.5 h-2.5 rounded-full {{ $fi === 0 ? 'bg-blue-600' : '' }}"></div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-2 text-xs text-gray-500">
                                            <span class="font-bold text-gray-700">{{ $flightCia }}</span>
                                            @if($flight['flight_number'] ?? null)
                                                <span class="text-gray-400 text-[11px]">voo {{ $flight['flight_number'] }}</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center">
                                            <div class="text-center shrink-0">
                                                <p class="text-base sm:text-lg font-bold text-gray-800 leading-tight">{{ $flight['departure_time'] ?? '' }}</p>
                                                <p class="text-[11px] font-medium text-gray-500">{{ $flight['departure_location'] ?? '' }}</p>
                                            </div>
                                            <div class="flex-1 mx-2 sm:mx-4 flex flex-col items-center min-w-[70px] sm:min-w-[80px]">
                                                @if($isDirect)
                                                    <span class="text-[11px] text-emerald-600 font-bold">Direto</span>
                                                @else
                                                    <button type="button" class="conn-toggle-btn text-[11px] text-amber-600 font-bold hover:text-amber-700 cursor-pointer"
                                                            data-target="conn-ob-{{ $groupIdx }}-{{ $fi }}">
                                                        {{ $connCount }} conexão ▾
                                                    </button>
                                                @endif
                                                <div class="w-full flex items-center gap-0.5 my-0.5">
                                                    <div class="w-1.5 h-1.5 rounded-full border border-gray-400"></div>
                                                    <div class="flex-1 border-t border-gray-300"></div>
                                                    @if(!$isDirect)
                                                        @for($s = 0; $s < $connCount; $s++)
                                                            <div class="w-1.5 h-1.5 rounded-full bg-gray-400"></div>
                                                            <div class="flex-1 border-t border-gray-300"></div>
                                                        @endfor
                                                    @endif
                                                    <svg class="w-3 h-3 text-gray-400 -ml-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                </div>
                                                <span class="text-[11px] text-gray-400 font-medium">{{ $flight['total_flight_duration'] ?? '' }}</span>
                                            </div>
                                            <div class="text-center shrink-0">
                                                <p class="text-base sm:text-lg font-bold text-gray-800 leading-tight">{{ $flight['arrival_time'] ?? '' }}</p>
                                                <p class="text-[11px] font-medium text-gray-500">{{ $flight['arrival_location'] ?? '' }}</p>
                                            </div>
                                            @if(!$isDirect)
                                                <button type="button" class="conn-toggle-btn hidden sm:inline-flex text-[11px] text-blue-600 hover:text-blue-700 font-medium whitespace-nowrap ml-3 shrink-0"
                                                        data-target="conn-ob-{{ $groupIdx }}-{{ $fi }}">
                                                    Detalhes
                                                </button>
                                            @endif
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
                <div class="border-t border-gray-100 pt-3">
                    <div class="flex items-center justify-between pb-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
                            <p class="text-sm font-bold text-gray-800">Volta</p>
                        </div>
                        @if($ibDateFormatted)
                            <span class="text-xs text-gray-500 font-medium">{{ $ibDateFormatted }}</span>
                        @endif
                    </div>

                    <div class="space-y-2 mb-2">
                        @foreach($ibFlights as $fi => $flight)
                            @php
                                $conns = $flight['connection'] ?? [];
                                $isDirect = !is_array($conns) || count($conns) <= 1;
                                $connCount = is_array($conns) ? max(0, count($conns) - 1) : 0;
                                $flightCia = $displayCia($flight['operator'] ?? '', $flight['flight_number'] ?? '');
                                $hiddenIb = $fi >= $collapseAfter;
                            @endphp
                            <div class="{{ $hiddenIb ? 'collapsed-ib-' . $groupIdx . ' hidden' : '' }}">
                                <label class="flight-option block p-3 sm:p-4 rounded-xl border cursor-pointer transition-all
                                    {{ $fi === 0 ? 'border-blue-400 bg-blue-50/60 shadow-sm' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                                    data-group="{{ $groupIdx }}" data-dir="ib">
                                    <input type="radio" name="group_{{ $groupIdx }}_ib" value="{{ $fi }}"
                                           class="sr-only ib-radio" data-group="{{ $groupIdx }}"
                                           {{ $fi === 0 ? 'checked' : '' }}>
                                    <div class="flex items-start gap-2 sm:gap-3">
                                        <div class="radio-dot w-5 h-5 rounded-full border-2 shrink-0 flex items-center justify-center mt-1
                                            {{ $fi === 0 ? 'border-blue-600' : 'border-gray-300' }}">
                                            <div class="radio-dot-inner w-2.5 h-2.5 rounded-full {{ $fi === 0 ? 'bg-blue-600' : '' }}"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-2 text-xs text-gray-500">
                                                <span class="font-bold text-gray-700">{{ $flightCia }}</span>
                                                @if($flight['flight_number'] ?? null)
                                                    <span class="text-gray-400 text-[11px]">voo {{ $flight['flight_number'] }}</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center">
                                                <div class="text-center shrink-0">
                                                    <p class="text-base sm:text-lg font-bold text-gray-800 leading-tight">{{ $flight['departure_time'] ?? '' }}</p>
                                                    <p class="text-[11px] font-medium text-gray-500">{{ $flight['departure_location'] ?? '' }}</p>
                                                </div>
                                                <div class="flex-1 mx-2 sm:mx-4 flex flex-col items-center min-w-[70px] sm:min-w-[80px]">
                                                    @if($isDirect)
                                                        <span class="text-[11px] text-emerald-600 font-bold">Direto</span>
                                                    @else
                                                        <button type="button" class="conn-toggle-btn text-[11px] text-amber-600 font-bold hover:text-amber-700 cursor-pointer"
                                                                data-target="conn-ib-{{ $groupIdx }}-{{ $fi }}">
                                                            {{ $connCount }} conexão ▾
                                                        </button>
                                                    @endif
                                                    <div class="w-full flex items-center gap-0.5 my-0.5">
                                                        <div class="w-1.5 h-1.5 rounded-full border border-gray-400"></div>
                                                        <div class="flex-1 border-t border-gray-300"></div>
                                                        @if(!$isDirect)
                                                            @for($s = 0; $s < $connCount; $s++)
                                                                <div class="w-1.5 h-1.5 rounded-full bg-gray-400"></div>
                                                                <div class="flex-1 border-t border-gray-300"></div>
                                                            @endfor
                                                        @endif
                                                        <svg class="w-3 h-3 text-gray-400 -ml-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                    </div>
                                                    <span class="text-[11px] text-gray-400 font-medium">{{ $flight['total_flight_duration'] ?? '' }}</span>
                                                </div>
                                                <div class="text-center shrink-0">
                                                    <p class="text-base sm:text-lg font-bold text-gray-800 leading-tight">{{ $flight['arrival_time'] ?? '' }}</p>
                                                    <p class="text-[11px] font-medium text-gray-500">{{ $flight['arrival_location'] ?? '' }}</p>
                                                </div>
                                                @if(!$isDirect)
                                                    <button type="button" class="conn-toggle-btn hidden sm:inline-flex text-[11px] text-blue-600 hover:text-blue-700 font-medium whitespace-nowrap ml-3 shrink-0"
                                                            data-target="conn-ib-{{ $groupIdx }}-{{ $fi }}">
                                                        Detalhes
                                                    </button>
                                                @endif
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

        {{-- Coluna direita: preço e resumo (desktop) --}}
        <div class="hidden lg:flex lg:w-60 shrink-0 border-l border-gray-100">
            <div class="sticky top-20 self-start w-full p-5 flex flex-col text-sm">
                @if($pixOn)
                    <div class="flex items-center gap-1.5 mb-3">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-xs font-bold text-emerald-700">Economia de R$ {{ number_format($pixSavings, 2, ',', '.') }}</span>
                    </div>

                    <p class="text-xs text-gray-400 line-through">R$ {{ number_format($totalPrice, 2, ',', '.') }}</p>
                    <div class="flex items-baseline gap-1.5">
                        <p class="text-2xl font-bold text-gray-900 whitespace-nowrap">R$ {{ number_format($pixPrice, 2, ',', '.') }}</p>
                        <span class="text-xs font-semibold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full whitespace-nowrap">No Pix</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mb-3">Por adulto{{ $hasInbound ? ', ida e volta' : '' }}</p>

                    <div class="space-y-1.5 text-xs text-gray-500 border-t border-gray-100 pt-3 mb-3">
                        <div class="flex justify-between">
                            <span>{{ $totalPax }} {{ $totalPax > 1 ? 'adultos' : 'adulto' }}</span>
                            <span class="text-gray-700 font-medium">R$ {{ number_format($basePrice, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Valor das taxas</span>
                            <span class="text-gray-700 font-medium">R$ {{ number_format($totalTax, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-emerald-600">
                            <span>Desconto no Pix</span>
                            <span class="font-medium">-R$ {{ number_format($pixSavings, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-gray-800 pt-1.5 border-t border-gray-100">
                            <span>{{ $totalPax > 1 ? 'Total por adulto' : 'Total' }} no Pix</span>
                            <span>R$ {{ number_format($pixPrice, 2, ',', '.') }}</span>
                        </div>
                        @if($totalPax > 1)
                        <div class="flex justify-between font-bold text-blue-700 bg-blue-50 -mx-1 px-1 py-1 rounded">
                            <span>Total ({{ $totalPax }}x)</span>
                            <span>R$ {{ number_format($pixAllPax, 2, ',', '.') }}</span>
                        </div>
                        @endif
                    </div>

                    <p class="text-[11px] text-gray-400 mb-4">Ou em até <b class="text-gray-600">{{ \App\Models\Setting::get('max_installments', 12) }}x</b> no cartão de crédito</p>
                @else
                    <p class="text-2xl font-bold text-gray-900 whitespace-nowrap mb-0.5">R$ {{ number_format($totalPrice, 2, ',', '.') }}</p>
                    <p class="text-[11px] text-gray-400 mb-3">Por adulto{{ $hasInbound ? ', ida e volta' : '' }}</p>

                    <div class="space-y-1.5 text-xs text-gray-500 border-t border-gray-100 pt-3 mb-4">
                        <div class="flex justify-between">
                            <span>{{ $totalPax }} {{ $totalPax > 1 ? 'adultos' : 'adulto' }}</span>
                            <span class="text-gray-700 font-medium">R$ {{ number_format($basePrice, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Valor das taxas</span>
                            <span class="text-gray-700 font-medium">R$ {{ number_format($totalTax, 2, ',', '.') }}</span>
                        </div>
                        @if($totalPax > 1)
                        <div class="flex justify-between font-bold text-blue-700 bg-blue-50 -mx-1 px-1 py-1 rounded pt-1.5 border-t border-gray-100">
                            <span>Total ({{ $totalPax }}x)</span>
                            <span>R$ {{ number_format($totalAllPax, 2, ',', '.') }}</span>
                        </div>
                        @endif
                    </div>
                @endif

                <form action="{{ route('search.select') }}" method="POST" class="group-form w-full" data-group="{{ $groupIdx }}">
                    @csrf
                    <input type="hidden" name="search_id" value="{{ $searchId }}">
                    <input type="hidden" name="outbound" class="selected-ob" value="{{ json_encode($obFlights[0] ?? [], JSON_UNESCAPED_UNICODE) }}">
                    @if($hasInbound)
                        <input type="hidden" name="inbound" class="selected-ib" value="{{ json_encode($ibFlights[0] ?? [], JSON_UNESCAPED_UNICODE) }}">
                    @endif
                    <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl transition-colors text-sm">
                        Comprar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
