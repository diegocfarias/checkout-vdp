@php
    $prefill = $prefill ?? null;
    $compact = $compact ?? false;
    $calendarPricesEnabled = \App\Models\Setting::get('calendar_prices_enabled', true);
    $calendarPricesMonths = (int) \App\Models\Setting::get('calendar_prices_months', 3);
    if ($prefill && isset($prefill['trip_type'])) {
        $tripType = $prefill['trip_type'];
    } elseif ($prefill && !empty($prefill['inbound_date'])) {
        $tripType = 'roundtrip';
    } elseif ($prefill) {
        $tripType = 'oneway';
    } else {
        $tripType = 'roundtrip';
    }
@endphp

<style>
.cal-price-tag {
    font-size: 8px;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
    margin-top: 1px;
    padding: 1px 3px;
    border-radius: 3px;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cal-price-green { color: #059669; background-color: #ecfdf5; }
.cal-price-yellow { color: #d97706; background-color: #fffbeb; }
.cal-price-red { color: #ef4444; background-color: #fef2f2; }
</style>
<form action="{{ route('search.results') }}" method="GET" id="search-form" class="{{ $compact ? 'w-full' : 'max-w-5xl mx-auto' }}">
    <div class="{{ $compact ? 'bg-white rounded-xl border border-gray-200 p-4 sm:p-5' : 'bg-white rounded-2xl shadow-2xl p-5 sm:p-7' }}">

        {{-- Trip Type: Segmented Control --}}
        <div class="flex justify-start mb-6">
            <div class="inline-flex bg-gray-100 rounded-full p-1 gap-0.5" id="trip-type-toggle">
                <button type="button" data-value="roundtrip"
                        class="trip-type-btn rounded-full px-5 py-2 text-sm font-medium transition-all duration-200 {{ $tripType === 'roundtrip' ? 'bg-white shadow-sm text-blue-700 font-semibold' : 'text-gray-500 hover:text-gray-700' }}">
                    Ida e volta
                </button>
                <button type="button" data-value="oneway"
                        class="trip-type-btn rounded-full px-5 py-2 text-sm font-medium transition-all duration-200 {{ $tripType === 'oneway' ? 'bg-white shadow-sm text-blue-700 font-semibold' : 'text-gray-500 hover:text-gray-700' }}">
                    Somente ida
                </button>
            </div>
            <input type="hidden" name="trip_type" id="input-trip-type" value="{{ $tripType }}">
        </div>

        {{-- Origem / Destino com Swap --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4 relative">
            <div class="relative">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5 tracking-wide">Origem</label>
                <div class="relative">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v4m0 12v4m-10-10h4m12 0h4"/></svg>
                    <input type="text" id="departure-input" placeholder="De onde você sai?"
                           class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                           autocomplete="off" required
                           @if($prefill) value="{{ $prefill['departure'] }}" @endif>
                </div>
                <input type="hidden" name="departure" id="departure-iata" @if($prefill) value="{{ $prefill['departure'] }}" @endif>
                <div id="departure-dropdown" class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-xl mt-1 hidden max-h-60 overflow-y-auto"></div>
            </div>

            {{-- Swap Button --}}
            <button type="button" id="swap-airports-btn"
                    class="hidden sm:flex absolute left-1/2 top-[38px] -translate-x-1/2 z-10 w-9 h-9 bg-white border-2 border-gray-200 rounded-full items-center justify-center text-gray-400 hover:text-blue-600 hover:border-blue-300 transition-all shadow-sm hover:shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </button>

            <div class="relative">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5 tracking-wide">Destino</label>
                <div class="relative">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <input type="text" id="arrival-input" placeholder="Para onde você vai?"
                           class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                           autocomplete="off" required
                           @if($prefill) value="{{ $prefill['arrival'] }}" @endif>
                </div>
                <input type="hidden" name="arrival" id="arrival-iata" @if($prefill) value="{{ $prefill['arrival'] }}" @endif>
                <div id="arrival-dropdown" class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-xl mt-1 hidden max-h-60 overflow-y-auto"></div>
            </div>
        </div>

        {{-- Datas / Passageiros / Classe --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5" style="overflow:visible;">
            <div class="relative sm:col-span-1" id="datepicker-container" style="overflow:visible;">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5 tracking-wide">Datas</label>
                <button type="button" id="datepicker-toggle"
                        class="w-full border border-gray-200 rounded-xl px-4 py-3.5 text-sm text-left bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none flex items-center gap-2.5 transition-all">
                    <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span id="datepicker-label" class="text-gray-500">Selecione as datas</span>
                </button>
                <input type="hidden" name="outbound_date" id="outbound-date" required
                       @if($prefill && !empty($prefill['outbound_date'])) value="{{ $prefill['outbound_date'] }}" @endif>
                <input type="hidden" name="inbound_date" id="inbound-date"
                       @if($prefill && !empty($prefill['inbound_date'])) value="{{ $prefill['inbound_date'] }}" @endif>

                {{-- Desktop Calendar Dropdown --}}
                <div id="datepicker-dropdown" class="hidden absolute z-[100] left-0 mt-2 bg-white border border-gray-200 rounded-2xl shadow-2xl p-5 sm:p-6"
                     style="width: min(calc(100vw - 2rem), 640px);">
                    <div class="flex items-center justify-between mb-5">
                        <button type="button" id="dp-prev" class="w-10 h-10 rounded-full hover:bg-gray-100 flex items-center justify-center transition-colors">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <div class="flex gap-10" id="dp-month-titles"></div>
                        <button type="button" id="dp-next" class="w-10 h-10 rounded-full hover:bg-gray-100 flex items-center justify-center transition-colors">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                    <div id="dp-calendars" class="flex gap-8 overflow-hidden"></div>
                    <div class="flex items-center justify-between mt-5 pt-4 border-t border-gray-100">
                        <div class="flex items-center gap-3" id="dp-chips"></div>
                        <div class="flex items-center gap-3">
                            <button type="button" id="dp-clear" class="text-sm text-gray-500 hover:text-gray-700 font-medium transition-colors">Limpar</button>
                            <button type="button" id="dp-confirm" class="text-sm bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg transition-colors">Confirmar</button>
                        </div>
                    </div>
                </div>

                {{-- Mobile Calendar Fullscreen Modal --}}
                <div id="datepicker-mobile" class="hidden fixed inset-0 z-[200] bg-white sm:hidden" style="overscroll-behavior: contain;">
                    <div class="flex flex-col h-full">
                        {{-- Header --}}
                        <div class="shrink-0 border-b border-gray-200 px-5 pt-5 pb-4">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-bold text-gray-800">Adicionar data</h2>
                                <button type="button" id="dp-mobile-close" class="w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div class="flex border-b border-gray-200">
                                <div class="flex-1 pb-3 border-b-2" id="dp-mob-ida-tab">
                                    <p class="text-xs text-gray-400 uppercase font-medium">Ida</p>
                                    <p class="text-sm font-semibold text-gray-800" id="dp-mob-ida-val">Selecionar</p>
                                </div>
                                <div class="flex-1 pb-3 pl-4" id="dp-mob-volta-tab">
                                    <p class="text-xs text-gray-400 uppercase font-medium">Volta</p>
                                    <p class="text-sm font-semibold text-gray-800" id="dp-mob-volta-val">Selecionar</p>
                                </div>
                            </div>
                        </div>
                        {{-- Scrollable months --}}
                        <div class="flex-1 overflow-y-auto px-5 pb-24" id="dp-mobile-months"></div>
                        {{-- Sticky footer --}}
                        <div class="shrink-0 border-t border-gray-200 bg-white px-5 py-4 flex gap-3">
                            <button type="button" id="dp-mobile-clear" class="flex-1 py-3 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">Limpar</button>
                            <button type="button" id="dp-mobile-confirm" class="flex-1 py-3 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors">Confirmar</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5 tracking-wide">Passageiros</label>
                <button type="button" id="pax-toggle"
                        class="w-full border border-gray-200 rounded-xl px-4 py-3.5 text-sm text-left bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none flex items-center gap-2.5 transition-all">
                    <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span id="pax-label" class="flex-1">1 Adulto</span>
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div id="pax-dropdown" class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-xl mt-1 hidden p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div><p class="text-sm font-medium text-gray-700">Adultos</p><p class="text-xs text-gray-400">12+ anos</p></div>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="changePax('adults',-1)" class="w-9 h-9 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 hover:border-gray-400 transition-colors text-lg">−</button>
                            <span id="pax-adults" class="text-sm font-bold w-5 text-center">{{ $prefill['adults'] ?? 1 }}</span>
                            <button type="button" onclick="changePax('adults',1)" class="w-9 h-9 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 hover:border-gray-400 transition-colors text-lg">+</button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div><p class="text-sm font-medium text-gray-700">Crianças</p><p class="text-xs text-gray-400">2-11 anos</p></div>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="changePax('children',-1)" class="w-9 h-9 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 hover:border-gray-400 transition-colors text-lg">−</button>
                            <span id="pax-children" class="text-sm font-bold w-5 text-center">{{ $prefill['children'] ?? 0 }}</span>
                            <button type="button" onclick="changePax('children',1)" class="w-9 h-9 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 hover:border-gray-400 transition-colors text-lg">+</button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div><p class="text-sm font-medium text-gray-700">Bebês</p><p class="text-xs text-gray-400">0-1 ano</p></div>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="changePax('infants',-1)" class="w-9 h-9 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 hover:border-gray-400 transition-colors text-lg">−</button>
                            <span id="pax-infants" class="text-sm font-bold w-5 text-center">{{ $prefill['infants'] ?? 0 }}</span>
                            <button type="button" onclick="changePax('infants',1)" class="w-9 h-9 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 hover:border-gray-400 transition-colors text-lg">+</button>
                        </div>
                    </div>
                    <button type="button" onclick="document.getElementById('pax-dropdown').classList.add('hidden')"
                            class="w-full text-center text-sm bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition-colors mt-1">Pronto</button>
                </div>
                <input type="hidden" name="adults" id="input-adults" value="{{ $prefill['adults'] ?? 1 }}">
                <input type="hidden" name="children" id="input-children" value="{{ $prefill['children'] ?? 0 }}">
                <input type="hidden" name="infants" id="input-infants" value="{{ $prefill['infants'] ?? 0 }}">
            </div>

            <div class="relative">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1.5 tracking-wide">Classe</label>
                <button type="button" id="cabin-toggle"
                        class="w-full border border-gray-200 rounded-xl px-4 py-3.5 text-sm text-left bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none flex items-center gap-2.5 transition-all">
                    <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    <span id="cabin-label" class="flex-1">{{ ($prefill['cabin'] ?? 'EC') === 'EX' ? 'Executiva' : 'Econômica' }}</span>
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div id="cabin-dropdown" class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-xl mt-1 hidden overflow-hidden">
                    <div class="cabin-option px-4 py-3.5 text-sm cursor-pointer hover:bg-blue-50 transition-colors {{ ($prefill['cabin'] ?? 'EC') === 'EC' ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-700' }}" data-value="EC">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Econômica
                        </span>
                    </div>
                    <div class="cabin-option px-4 py-3.5 text-sm cursor-pointer hover:bg-blue-50 transition-colors border-t border-gray-100 {{ ($prefill['cabin'] ?? 'EC') === 'EX' ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-700' }}" data-value="EX">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                            Executiva
                        </span>
                    </div>
                </div>
                <input type="hidden" name="cabin" id="input-cabin" value="{{ $prefill['cabin'] ?? 'EC' }}">
            </div>
        </div>

        <button type="submit" id="btn-search"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-4 rounded-xl transition-all text-base flex items-center justify-center gap-2 shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            Buscar voos
        </button>
    </div>
</form>

<script>
(function() {
    var prefillData = @json($prefill);

    // ========== TRIP TYPE TOGGLE ==========
    var tripInput = document.getElementById('input-trip-type');
    document.querySelectorAll('.trip-type-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            tripInput.value = btn.getAttribute('data-value');
            document.querySelectorAll('.trip-type-btn').forEach(function(b) {
                b.classList.remove('bg-white', 'shadow-sm', 'text-blue-700', 'font-semibold');
                b.classList.add('text-gray-500');
            });
            btn.classList.remove('text-gray-500');
            btn.classList.add('bg-white', 'shadow-sm', 'text-blue-700', 'font-semibold');
            toggleInbound();
        });
    });

    // ========== AIRPORTS AUTOCOMPLETE ==========
    var airportsData = null;
    var airportsLoading = false;
    var airportsCallbacks = [];

    function loadAirports(cb) {
        if (airportsData) { cb(airportsData); return; }
        airportsCallbacks.push(cb);
        if (airportsLoading) return;
        airportsLoading = true;
        fetch('/data/airports.json')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                airportsData = data;
                airportsCallbacks.forEach(function(fn) { fn(data); });
                airportsCallbacks = [];
            })
            .catch(function() { airportsLoading = false; });
    }

    function normalizeStr(s) {
        return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function searchAirports(airports, term) {
        var q = normalizeStr(term);
        var results = [];
        for (var i = 0; i < airports.length && results.length < 15; i++) {
            var a = airports[i];
            if (normalizeStr(a.c).indexOf(q) !== -1 || normalizeStr(a.d).indexOf(q) !== -1) {
                results.push(a); continue;
            }
            for (var j = 0; j < a.t.length; j++) {
                if (normalizeStr(a.t[j]).indexOf(q) !== -1) { results.push(a); break; }
            }
        }
        return results;
    }

    var debounceTimers = {};
    function debounce(id, fn, delay) {
        clearTimeout(debounceTimers[id]);
        debounceTimers[id] = setTimeout(fn, delay);
    }

    function setupAutocomplete(inputId, hiddenId, dropdownId) {
        var input = document.getElementById(inputId);
        var hidden = document.getElementById(hiddenId);
        var dropdown = document.getElementById(dropdownId);
        input.addEventListener('focus', function() { loadAirports(function(){}); });
        input.addEventListener('input', function() {
            var term = input.value.trim();
            hidden.value = '';
            if (term.length < 2) { dropdown.classList.add('hidden'); return; }
            debounce(inputId, function() {
                loadAirports(function(airports) {
                    var results = searchAirports(airports, term);
                    if (results.length === 0) {
                        dropdown.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400">Nenhum resultado</div>';
                        dropdown.classList.remove('hidden');
                        return;
                    }
                    dropdown.innerHTML = '';
                    results.forEach(function(item) {
                        var div = document.createElement('div');
                        div.className = 'px-4 py-3 text-sm hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0 transition-colors';
                        div.innerHTML = '<span class="font-semibold text-gray-800">' + item.c + '</span> <span class="text-gray-500">' + item.d.replace(' (' + item.c + ')', '') + '</span>';
                        div.addEventListener('click', function() {
                            input.value = item.d;
                            hidden.value = item.c;
                            dropdown.classList.add('hidden');
                        });
                        dropdown.appendChild(div);
                    });
                    dropdown.classList.remove('hidden');
                });
            }, 150);
        });
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('hidden');
        });
    }
    setupAutocomplete('departure-input', 'departure-iata', 'departure-dropdown');
    setupAutocomplete('arrival-input', 'arrival-iata', 'arrival-dropdown');

    // ========== SWAP AIRPORTS ==========
    var swapBtn = document.getElementById('swap-airports-btn');
    if (swapBtn) {
        swapBtn.addEventListener('click', function() {
            var depInput = document.getElementById('departure-input');
            var arrInput = document.getElementById('arrival-input');
            var depHidden = document.getElementById('departure-iata');
            var arrHidden = document.getElementById('arrival-iata');
            var tmpVal = depInput.value;
            var tmpHidden = depHidden.value;
            depInput.value = arrInput.value;
            depHidden.value = arrHidden.value;
            arrInput.value = tmpVal;
            arrHidden.value = tmpHidden;
            swapBtn.classList.add('rotate-180');
            setTimeout(function() { swapBtn.classList.remove('rotate-180'); }, 300);
        });
    }

    // ========== PASSENGERS ==========
    var pax = {
        adults: parseInt(document.getElementById('input-adults').value) || 1,
        children: parseInt(document.getElementById('input-children').value) || 0,
        infants: parseInt(document.getElementById('input-infants').value) || 0
    };
    window.changePax = function(type, delta) {
        pax[type] = Math.max(type === 'adults' ? 1 : 0, Math.min(9, pax[type] + delta));
        document.getElementById('pax-' + type).textContent = pax[type];
        document.getElementById('input-' + type).value = pax[type];
        updatePaxLabel();
    };
    function updatePaxLabel() {
        var parts = [];
        if (pax.adults > 0) parts.push(pax.adults + (pax.adults === 1 ? ' Adulto' : ' Adultos'));
        if (pax.children > 0) parts.push(pax.children + (pax.children === 1 ? ' Criança' : ' Crianças'));
        if (pax.infants > 0) parts.push(pax.infants + (pax.infants === 1 ? ' Bebê' : ' Bebês'));
        document.getElementById('pax-label').textContent = parts.join(', ');
    }
    updatePaxLabel();

    document.getElementById('pax-toggle').addEventListener('click', function() {
        document.getElementById('pax-dropdown').classList.toggle('hidden');
    });
    document.addEventListener('click', function(e) {
        var toggle = document.getElementById('pax-toggle');
        var dd = document.getElementById('pax-dropdown');
        if (!toggle.contains(e.target) && !dd.contains(e.target)) dd.classList.add('hidden');
    });

    // ========== CABIN ==========
    var cabinLabels = { EC: 'Econômica', EX: 'Executiva' };
    document.getElementById('cabin-toggle').addEventListener('click', function() {
        document.getElementById('cabin-dropdown').classList.toggle('hidden');
    });
    document.querySelectorAll('.cabin-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            var val = opt.getAttribute('data-value');
            document.getElementById('input-cabin').value = val;
            document.getElementById('cabin-label').textContent = cabinLabels[val];
            document.querySelectorAll('.cabin-option').forEach(function(o) {
                o.classList.remove('bg-blue-50', 'text-blue-700', 'font-semibold');
                o.classList.add('text-gray-700');
            });
            opt.classList.remove('text-gray-700');
            opt.classList.add('bg-blue-50', 'text-blue-700', 'font-semibold');
            document.getElementById('cabin-dropdown').classList.add('hidden');
        });
    });
    document.addEventListener('click', function(e) {
        var cToggle = document.getElementById('cabin-toggle');
        var cDd = document.getElementById('cabin-dropdown');
        if (!cToggle.contains(e.target) && !cDd.contains(e.target)) cDd.classList.add('hidden');
    });

    window.toggleInbound = function() {
        var rt = isRoundtrip();
        if (!rt) {
            dpInbound = null;
            document.getElementById('inbound-date').value = '';
        }
        dpRender();
        dpUpdateLabel();
        dpUpdateChips();
    };

    // ========== DATEPICKER ==========
    var MONTHS = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    var MONTHS_SHORT = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    var DAYS_SHORT = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    var DAYS_LETTER = ['D','S','T','Q','Q','S','S'];
    var MOBILE_MONTHS_COUNT = 11;
    var today = new Date(); today.setHours(0,0,0,0);
    var dpViewMonth = today.getMonth();
    var dpViewYear = today.getFullYear();
    var dpOutbound = null;
    var dpInbound = null;
    var dpHover = null;
    var dpOpen = false;

    function parseDate(str) {
        if (!str) return null;
        var parts = str.split('-');
        if (parts.length !== 3) return null;
        return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    }

    var obVal = document.getElementById('outbound-date').value;
    var ibVal = document.getElementById('inbound-date').value;
    if (obVal) {
        dpOutbound = parseDate(obVal);
        if (dpOutbound) { dpViewMonth = dpOutbound.getMonth(); dpViewYear = dpOutbound.getFullYear(); }
    }
    if (ibVal) { dpInbound = parseDate(ibVal); }

    function isRoundtrip() {
        return document.getElementById('input-trip-type').value === 'roundtrip';
    }

    function dateKey(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }

    function formatDisplay(d) {
        return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
    }

    function formatShort(d) {
        return String(d.getDate()).padStart(2,'0') + ' ' + MONTHS_SHORT[d.getMonth()];
    }

    function sameDay(a, b) {
        return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    function isMobile() { return window.innerWidth < 640; }

    function isInRange(date) {
        if (dpOutbound && !dpInbound && dpHover && date > dpOutbound && date <= dpHover) return true;
        if (dpOutbound && dpInbound && date > dpOutbound && date < dpInbound) return true;
        return false;
    }

    var maxDate = new Date(today.getFullYear(), today.getMonth() + 11, today.getDate());
    maxDate.setHours(0,0,0,0);

    var calendarPricesFeatureEnabled = @json((bool) $calendarPricesEnabled);
    var calendarPricesMonths = @json($calendarPricesMonths);
    var calendarPrices = {};
    var calendarPriceThresholds = { p33: null, p66: null };
    var calPriceFetchId = 0;
    var calPriceDirection = 'outbound';

    function computePriceThresholds() {
        var vals = [];
        Object.keys(calendarPrices).forEach(function(k) {
            if (calendarPrices[k] != null) vals.push(calendarPrices[k]);
        });
        vals.sort(function(a, b) { return a - b; });
        if (vals.length > 2) {
            calendarPriceThresholds.p33 = vals[Math.floor(vals.length * 0.33)];
            calendarPriceThresholds.p66 = vals[Math.floor(vals.length * 0.66)];
        } else {
            calendarPriceThresholds.p33 = null;
            calendarPriceThresholds.p66 = null;
        }
    }

    function getPriceColorClass(price) {
        if (price == null || calendarPriceThresholds.p33 == null) return '';
        if (price <= calendarPriceThresholds.p33) return 'cal-price-green';
        if (price > calendarPriceThresholds.p66) return 'cal-price-red';
        return 'cal-price-yellow';
    }

    function formatCalPrice(price) {
        if (price >= 1000) return (price / 1000).toFixed(1).replace('.', ',') + 'k';
        return Math.round(price).toString();
    }

    function getCurrentPriceDirection() {
        if (dpOutbound && !dpInbound && isRoundtrip()) return 'inbound';
        return 'outbound';
    }

    function fetchCalendarPrices(monthFrom, yearFrom, monthTo, yearTo) {
        if (!calendarPricesFeatureEnabled) return;
        var dep = document.getElementById('departure-iata').value;
        var arr = document.getElementById('arrival-iata').value;
        if (!dep || !arr) return;

        var direction = getCurrentPriceDirection();
        var fetchDep = direction === 'inbound' ? arr : dep;
        var fetchArr = direction === 'inbound' ? dep : arr;

        var dateFrom = yearFrom + '-' + String(monthFrom + 1).padStart(2, '0') + '-01';
        var lastDay = new Date(yearTo, monthTo + 1, 0).getDate();
        var dateTo = yearTo + '-' + String(monthTo + 1).padStart(2, '0') + '-' + String(lastDay).padStart(2, '0');

        var cabin = document.getElementById('input-cabin').value || 'EC';
        var adults = parseInt(document.getElementById('input-adults').value) || 1;
        var children = parseInt(document.getElementById('input-children').value) || 0;
        var infants = parseInt(document.getElementById('input-infants').value) || 0;

        var qs = 'departure=' + encodeURIComponent(fetchDep)
            + '&arrival=' + encodeURIComponent(fetchArr)
            + '&cabin=' + encodeURIComponent(cabin)
            + '&adults=' + adults
            + '&children=' + children
            + '&infants=' + infants
            + '&date_from=' + dateFrom
            + '&date_to=' + dateTo
            + '&trip_type=oneway'
            + '&inbound_offset=0';

        calPriceFetchId++;
        var myFetchId = calPriceFetchId;
        calPriceDirection = direction;

        fetch('/api/date-prices?' + qs)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (myFetchId !== calPriceFetchId) return;
                if (data.prices) {
                    Object.keys(data.prices).forEach(function(k) {
                        if (data.prices[k] != null) calendarPrices[k] = data.prices[k];
                    });
                    computePriceThresholds();
                    updateCalendarPriceCells();
                }
            })
            .catch(function() {});
    }

    function clearCalendarPrices() {
        calendarPrices = {};
        calendarPriceThresholds = { p33: null, p66: null };
        calPriceFetchId++;
        updateCalendarPriceCells();
    }

    function updateCalendarPriceCells() {
        var containers = ['dp-calendars', 'dp-mobile-months'];
        containers.forEach(function(cid) {
            var cells = document.querySelectorAll('#' + cid + ' [data-date]');
            for (var i = 0; i < cells.length; i++) {
                var wrapper = cells[i];
                var dk = wrapper.getAttribute('data-date');
                var existing = wrapper.querySelector('.cal-price-tag');
                if (existing) existing.remove();

                var price = calendarPrices[dk];
                if (price != null) {
                    var tag = document.createElement('span');
                    tag.className = 'cal-price-tag ' + getPriceColorClass(price);
                    tag.textContent = 'R$' + formatCalPrice(price);
                    wrapper.appendChild(tag);
                }
            }
        });
    }

    function buildCellClasses(date) {
        var isPast = date < today || date > maxDate;
        var isToday = sameDay(date, today);
        var isStart = sameDay(date, dpOutbound);
        var isEnd = sameDay(date, dpInbound);
        var inRange = isInRange(date);

        var outer = 'flex flex-col items-center justify-start relative pt-1 min-h-[3.5rem] ';
        var inner = 'w-9 h-9 flex items-center justify-center text-sm font-medium transition-all duration-150 ';

        if (isPast) {
            return { outer: outer, inner: inner + 'text-gray-300 cursor-not-allowed rounded-full' };
        }

        if (isStart) {
            outer += (dpInbound || (dpHover && isRoundtrip())) ? 'bg-blue-50 rounded-l-xl' : '';
            inner += 'bg-blue-600 text-white rounded-full shadow-sm';
        } else if (isEnd) {
            outer += 'bg-blue-50 rounded-r-xl';
            inner += 'bg-blue-600 text-white rounded-full shadow-sm';
        } else if (inRange) {
            outer += 'bg-blue-50';
            inner += 'text-blue-800 hover:bg-blue-200 cursor-pointer rounded-full';
        } else {
            inner += 'text-gray-700 hover:bg-gray-100 cursor-pointer rounded-full';
            if (isToday) {
                inner += ' ring-1 ring-blue-400';
            }
        }

        return { outer: outer, inner: inner };
    }

    function buildMonth(year, month, useLongDays) {
        var wrap = document.createElement('div');
        wrap.className = 'flex-1 min-w-0';

        var dayNames = useLongDays ? DAYS_SHORT : DAYS_LETTER;
        var header = document.createElement('div');
        header.className = 'grid grid-cols-7 mb-2';
        dayNames.forEach(function(d) {
            var span = document.createElement('span');
            span.className = 'text-center text-xs font-medium text-gray-400 py-2';
            span.textContent = d;
            header.appendChild(span);
        });
        wrap.appendChild(header);

        var grid = document.createElement('div');
        grid.className = 'grid grid-cols-7';

        var first = new Date(year, month, 1);
        var startDay = first.getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();

        for (var blank = 0; blank < startDay; blank++) {
            var empty = document.createElement('div');
            empty.className = 'h-14';
            grid.appendChild(empty);
        }

        for (var d = 1; d <= daysInMonth; d++) {
            var date = new Date(year, month, d);
            var cls = buildCellClasses(date);

            var cellWrapper = document.createElement('div');
            cellWrapper.setAttribute('data-date', dateKey(date));
            cellWrapper.className = cls.outer;

            var isStart = sameDay(date, dpOutbound);
            var isEnd = sameDay(date, dpInbound);
            if (isStart || isEnd) {
                var labelTag = document.createElement('span');
                labelTag.className = 'absolute -top-3.5 left-1/2 -translate-x-1/2 text-[10px] font-bold uppercase whitespace-nowrap ' + (isStart ? 'text-blue-600' : 'text-blue-600');
                labelTag.textContent = isStart ? 'IDA' : 'VOLTA';
                cellWrapper.appendChild(labelTag);
            }

            var cell = document.createElement('button');
            cell.type = 'button';
            cell.textContent = d;
            cell.className = cls.inner;

            if (date >= today) {
                (function(dt) {
                    cell.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        dpSelectDate(dt);
                    });
                    cell.addEventListener('pointerenter', function(e) {
                        if (e.pointerType === 'touch') return;
                        if (dpOutbound && !dpInbound && isRoundtrip()) {
                            dpHover = dt;
                            dpUpdateAllCells();
                        }
                    });
                })(date);
            }
            cellWrapper.appendChild(cell);

            var dk = dateKey(date);
            var cachedPrice = calendarPrices[dk];
            if (cachedPrice != null && !(date < today || date > maxDate)) {
                var priceTag = document.createElement('span');
                priceTag.className = 'cal-price-tag ' + getPriceColorClass(cachedPrice);
                priceTag.textContent = 'R$' + formatCalPrice(cachedPrice);
                cellWrapper.appendChild(priceTag);
            }

            grid.appendChild(cellWrapper);
        }
        wrap.appendChild(grid);
        return wrap;
    }

    function dpUpdateAllCells() {
        var containers = ['dp-calendars', 'dp-mobile-months'];
        containers.forEach(function(cid) {
            var cells = document.querySelectorAll('#' + cid + ' [data-date]');
            for (var i = 0; i < cells.length; i++) {
                var wrapper = cells[i];
                var dk = wrapper.getAttribute('data-date');
                var parts = dk.split('-');
                var dt = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                var cls = buildCellClasses(dt);
                wrapper.className = cls.outer;
                var btn = wrapper.querySelector('button');
                if (btn) btn.className = cls.inner;
                var oldLabel = wrapper.querySelector('span.absolute');
                if (oldLabel) oldLabel.remove();
                var isStart = sameDay(dt, dpOutbound);
                var isEnd = sameDay(dt, dpInbound);
                if (isStart || isEnd) {
                    var labelTag = document.createElement('span');
                    labelTag.className = 'absolute -top-3.5 left-1/2 -translate-x-1/2 text-[10px] font-bold uppercase whitespace-nowrap ' + (isStart ? 'text-blue-600' : 'text-blue-600');
                    labelTag.textContent = isStart ? 'IDA' : 'VOLTA';
                    wrapper.insertBefore(labelTag, wrapper.firstChild);
                }

                var oldPriceTag = wrapper.querySelector('.cal-price-tag');
                if (oldPriceTag) oldPriceTag.remove();
                var price = calendarPrices[dk];
                var isPast = dt < today || dt > maxDate;
                if (price != null && !isPast) {
                    var priceTag = document.createElement('span');
                    priceTag.className = 'cal-price-tag ' + getPriceColorClass(price);
                    priceTag.textContent = 'R$' + formatCalPrice(price);
                    wrapper.appendChild(priceTag);
                }
            }
        });
        dpUpdateChips();
        dpUpdateMobileTabs();
    }

    // Desktop render
    function dpRenderDesktop() {
        var titles = document.getElementById('dp-month-titles');
        var cals = document.getElementById('dp-calendars');
        titles.innerHTML = '';
        cals.innerHTML = '';
        for (var i = 0; i < 2; i++) {
            var m = (dpViewMonth + i) % 12;
            var y = dpViewYear + Math.floor((dpViewMonth + i) / 12);
            var title = document.createElement('span');
            title.className = 'text-base font-bold text-gray-800';
            title.textContent = MONTHS[m] + ' ' + y;
            titles.appendChild(title);
            cals.appendChild(buildMonth(y, m, false));
        }
        dpUpdateChips();
    }

    // Mobile render
    function dpRenderMobile() {
        var container = document.getElementById('dp-mobile-months');
        container.innerHTML = '';
        var startM = today.getMonth();
        var startY = today.getFullYear();
        for (var i = 0; i < MOBILE_MONTHS_COUNT; i++) {
            var m = (startM + i) % 12;
            var y = startY + Math.floor((startM + i) / 12);

            var section = document.createElement('div');
            section.className = 'pt-8 pb-4';

            var title = document.createElement('h3');
            title.className = 'text-lg font-bold text-gray-800 text-center mb-4';
            title.textContent = MONTHS[m] + ' ' + y;
            section.appendChild(title);

            section.appendChild(buildMonth(y, m, true));
            container.appendChild(section);
        }
        dpUpdateMobileTabs();
    }

    function dpRender() {
        if (isMobile()) {
            dpRenderMobile();
        } else {
            dpRenderDesktop();
        }
    }

    function dpUpdateMobileTabs() {
        var idaVal = document.getElementById('dp-mob-ida-val');
        var voltaVal = document.getElementById('dp-mob-volta-val');
        var idaTab = document.getElementById('dp-mob-ida-tab');
        var voltaTab = document.getElementById('dp-mob-volta-tab');
        if (!idaVal) return;

        idaVal.textContent = dpOutbound ? formatDisplay(dpOutbound) : 'Selecionar';
        voltaVal.textContent = dpInbound ? formatDisplay(dpInbound) : 'Selecionar';

        idaTab.className = 'flex-1 pb-3' + (!dpOutbound || (dpOutbound && !dpInbound && !isRoundtrip()) ? ' border-b-2 border-blue-600' : ' border-b-2 border-transparent');
        voltaTab.className = 'flex-1 pb-3 pl-4' + (dpOutbound && !dpInbound && isRoundtrip() ? ' border-b-2 border-blue-600' : ' border-b-2 border-transparent');

        if (isRoundtrip()) {
            voltaTab.style.display = '';
        } else {
            voltaTab.style.display = 'none';
        }
    }

    function dpSelectDate(date) {
        var wasSelectingOutbound = !dpOutbound || (dpOutbound && dpInbound) || !isRoundtrip();
        var switchedToInbound = false;

        if (wasSelectingOutbound) {
            dpOutbound = date;
            dpInbound = null;
            dpHover = null;
            if (isRoundtrip()) switchedToInbound = true;
        } else {
            if (date < dpOutbound) {
                dpOutbound = date;
                dpInbound = null;
                switchedToInbound = true;
            } else if (sameDay(date, dpOutbound)) {
                return;
            } else {
                dpInbound = date;
                dpHover = null;
            }
        }
        document.getElementById('outbound-date').value = dpOutbound ? dateKey(dpOutbound) : '';
        document.getElementById('inbound-date').value = dpInbound ? dateKey(dpInbound) : '';
        dpUpdateLabel();
        dpUpdateAllCells();

        if (switchedToInbound) {
            clearCalendarPrices();
            dpFetchVisiblePrices();
        }

        if (!isMobile()) {
            if (!isRoundtrip() && dpOutbound) {
                setTimeout(function() { dpToggle(false); }, 300);
            }
            if (isRoundtrip() && dpOutbound && dpInbound) {
                setTimeout(function() { dpToggle(false); }, 300);
            }
        }
    }

    function dpUpdateLabel() {
        var label = document.getElementById('datepicker-label');
        if (!dpOutbound) {
            label.textContent = 'Selecione as datas';
            label.className = 'text-gray-500';
        } else if (!isRoundtrip()) {
            label.textContent = formatDisplay(dpOutbound);
            label.className = 'text-gray-800 font-medium';
        } else if (!dpInbound) {
            label.textContent = formatDisplay(dpOutbound) + '  →  ...';
            label.className = 'text-gray-800 font-medium';
        } else {
            label.textContent = formatDisplay(dpOutbound) + '  →  ' + formatDisplay(dpInbound);
            label.className = 'text-gray-800 font-medium';
        }
    }
    dpUpdateLabel();

    function dpUpdateChips() {
        var container = document.getElementById('dp-chips');
        if (!container) return;
        container.innerHTML = '';
        if (dpOutbound) {
            var chip1 = document.createElement('span');
            chip1.className = 'inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1.5 rounded-full';
            chip1.innerHTML = '<span class="text-[10px] font-bold uppercase">IDA</span> ' + formatShort(dpOutbound);
            container.appendChild(chip1);
        }
        if (dpInbound) {
            var chip2 = document.createElement('span');
            chip2.className = 'inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1.5 rounded-full';
            chip2.innerHTML = '<span class="text-[10px] font-bold uppercase">VOLTA</span> ' + formatShort(dpInbound);
            container.appendChild(chip2);
        }
        if (!dpOutbound && !dpInbound) {
            var hint = document.createElement('span');
            hint.className = 'text-xs text-gray-400';
            hint.textContent = 'Selecione a data de ida';
            container.appendChild(hint);
        } else if (dpOutbound && !dpInbound && isRoundtrip()) {
            var hint2 = document.createElement('span');
            hint2.className = 'text-xs text-gray-400';
            hint2.textContent = '← Selecione a volta';
            container.appendChild(hint2);
        }
    }

    function dpFetchVisiblePrices() {
        var startM = today.getMonth();
        var startY = today.getFullYear();
        var maxEndIdx = calendarPricesMonths - 1;

        if (isMobile()) {
            var endIdx = Math.min(MOBILE_MONTHS_COUNT - 1, maxEndIdx);
            var endM = (startM + endIdx) % 12;
            var endY = startY + Math.floor((startM + endIdx) / 12);
            fetchCalendarPrices(startM, startY, endM, endY);
        } else {
            var m1 = dpViewMonth % 12;
            var y1 = dpViewYear + Math.floor(dpViewMonth / 12);
            var m2 = (dpViewMonth + 1) % 12;
            var y2 = dpViewYear + Math.floor((dpViewMonth + 1) / 12);
            var limitM = (startM + maxEndIdx) % 12;
            var limitY = startY + Math.floor((startM + maxEndIdx) / 12);
            if (y1 > limitY || (y1 === limitY && m1 > limitM)) return;
            if (y2 > limitY || (y2 === limitY && m2 > limitM)) {
                m2 = limitM;
                y2 = limitY;
            }
            fetchCalendarPrices(m1, y1, m2, y2);
        }
    }

    function dpToggle(force) {
        dpOpen = force !== undefined ? force : !dpOpen;
        if (isMobile()) {
            var mob = document.getElementById('datepicker-mobile');
            mob.classList.toggle('hidden', !dpOpen);
            if (dpOpen) {
                document.body.classList.add('overflow-hidden');
                dpRenderMobile();
                dpFetchVisiblePrices();
            } else {
                document.body.classList.remove('overflow-hidden');
            }
        } else {
            var dd = document.getElementById('datepicker-dropdown');
            dd.classList.toggle('hidden', !dpOpen);
            if (dpOpen) {
                dpRenderDesktop();
                dpFetchVisiblePrices();
            }
        }
    }

    document.getElementById('datepicker-toggle').addEventListener('click', function(e) {
        e.stopPropagation();
        dpToggle();
    });
    document.getElementById('dp-prev').addEventListener('click', function(e) {
        e.stopPropagation();
        dpViewMonth--;
        if (dpViewMonth < 0) { dpViewMonth = 11; dpViewYear--; }
        dpRenderDesktop();
        dpFetchVisiblePrices();
    });
    var maxMonth = today.getMonth() + 10;
    var maxMonthVal = maxMonth % 12;
    var maxYearVal = today.getFullYear() + Math.floor(maxMonth / 12);

    document.getElementById('dp-next').addEventListener('click', function(e) {
        e.stopPropagation();
        var nextM = dpViewMonth + 1;
        var nextY = dpViewYear;
        if (nextM > 11) { nextM = 0; nextY++; }
        var secondM = (nextM + 1) % 12;
        var secondY = nextY + Math.floor((nextM + 1) / 12);
        if (secondY > maxYearVal || (secondY === maxYearVal && secondM > maxMonthVal)) return;
        dpViewMonth = nextM;
        dpViewYear = nextY;
        dpRenderDesktop();
        dpFetchVisiblePrices();
    });
    document.getElementById('dp-clear').addEventListener('click', function(e) {
        e.stopPropagation();
        dpOutbound = null; dpInbound = null; dpHover = null;
        document.getElementById('outbound-date').value = '';
        document.getElementById('inbound-date').value = '';
        dpUpdateLabel();
        clearCalendarPrices();
        dpRenderDesktop();
        dpFetchVisiblePrices();
    });
    document.getElementById('dp-confirm').addEventListener('click', function(e) {
        e.stopPropagation();
        dpToggle(false);
    });
    // Mobile buttons
    document.getElementById('dp-mobile-close').addEventListener('click', function() { dpToggle(false); });
    document.getElementById('dp-mobile-clear').addEventListener('click', function() {
        dpOutbound = null; dpInbound = null; dpHover = null;
        document.getElementById('outbound-date').value = '';
        document.getElementById('inbound-date').value = '';
        dpUpdateLabel();
        clearCalendarPrices();
        dpRenderMobile();
        dpFetchVisiblePrices();
    });
    document.getElementById('dp-mobile-confirm').addEventListener('click', function() { dpToggle(false); });

    document.addEventListener('click', function(e) {
        var container = document.getElementById('datepicker-container');
        if (dpOpen && !isMobile() && !container.contains(e.target)) dpToggle(false);
    });

    // ========== FORM SUBMIT ==========
    document.getElementById('search-form').addEventListener('submit', function(e) {
        if (!document.getElementById('departure-iata').value) {
            e.preventDefault(); alert('Selecione o aeroporto de origem.'); return;
        }
        if (!document.getElementById('arrival-iata').value) {
            e.preventDefault(); alert('Selecione o aeroporto de destino.'); return;
        }
        if (!document.getElementById('outbound-date').value) {
            e.preventDefault(); alert('Selecione a data de ida.'); return;
        }
        if (isRoundtrip() && !document.getElementById('inbound-date').value) {
            e.preventDefault(); alert('Selecione a data de volta.'); return;
        }
        try {
            localStorage.setItem('vdp_last_search', JSON.stringify({
                departure_iata: document.getElementById('departure-iata').value,
                departure_label: document.getElementById('departure-input').value,
                arrival_iata: document.getElementById('arrival-iata').value,
                arrival_label: document.getElementById('arrival-input').value,
                outbound_date: document.getElementById('outbound-date').value,
                inbound_date: document.getElementById('inbound-date').value,
                adults: parseInt(document.getElementById('input-adults').value) || 1,
                children: parseInt(document.getElementById('input-children').value) || 0,
                infants: parseInt(document.getElementById('input-infants').value) || 0,
                cabin: document.getElementById('input-cabin').value,
                trip_type: document.getElementById('input-trip-type').value
            }));
        } catch(ex) {}
        showTravelLoading({
            title: 'Buscando os melhores voos...',
            messages: [
                'Consultando companhias aéreas...',
                'Verificando disponibilidade...',
                'Comparando preços...',
                'Quase lá...'
            ],
            timeoutMs: 60000
        });
    });

    // ========== RESTORE SAVED SEARCH ==========
    if (!prefillData) {
        try {
            var saved = JSON.parse(localStorage.getItem('vdp_last_search'));
            if (saved) {
                if (saved.departure_iata) {
                    document.getElementById('departure-iata').value = saved.departure_iata;
                    document.getElementById('departure-input').value = saved.departure_label || saved.departure_iata;
                }
                if (saved.arrival_iata) {
                    document.getElementById('arrival-iata').value = saved.arrival_iata;
                    document.getElementById('arrival-input').value = saved.arrival_label || saved.arrival_iata;
                }
                if (saved.trip_type) {
                    tripInput.value = saved.trip_type;
                    document.querySelectorAll('.trip-type-btn').forEach(function(b) {
                        if (b.getAttribute('data-value') === saved.trip_type) {
                            b.classList.remove('text-gray-500');
                            b.classList.add('bg-white', 'shadow-sm', 'text-blue-700', 'font-semibold');
                        } else {
                            b.classList.remove('bg-white', 'shadow-sm', 'text-blue-700', 'font-semibold');
                            b.classList.add('text-gray-500');
                        }
                    });
                }
                if (saved.outbound_date) {
                    document.getElementById('outbound-date').value = saved.outbound_date;
                    dpOutbound = parseDate(saved.outbound_date);
                    if (dpOutbound) { dpViewMonth = dpOutbound.getMonth(); dpViewYear = dpOutbound.getFullYear(); }
                }
                if (saved.inbound_date) {
                    document.getElementById('inbound-date').value = saved.inbound_date;
                    dpInbound = parseDate(saved.inbound_date);
                }
                dpUpdateLabel();
                if (saved.adults) { pax.adults = saved.adults; document.getElementById('pax-adults').textContent = saved.adults; document.getElementById('input-adults').value = saved.adults; }
                if (saved.children) { pax.children = saved.children; document.getElementById('pax-children').textContent = saved.children; document.getElementById('input-children').value = saved.children; }
                if (saved.infants) { pax.infants = saved.infants; document.getElementById('pax-infants').textContent = saved.infants; document.getElementById('input-infants').value = saved.infants; }
                updatePaxLabel();
                if (saved.cabin) {
                    document.getElementById('input-cabin').value = saved.cabin;
                    document.getElementById('cabin-label').textContent = cabinLabels[saved.cabin] || 'Econômica';
                    document.querySelectorAll('.cabin-option').forEach(function(o) {
                        if (o.getAttribute('data-value') === saved.cabin) {
                            o.classList.remove('text-gray-700');
                            o.classList.add('bg-blue-50', 'text-blue-700', 'font-semibold');
                        } else {
                            o.classList.remove('bg-blue-50', 'text-blue-700', 'font-semibold');
                            o.classList.add('text-gray-700');
                        }
                    });
                }
            }
        } catch(ex) {}
    }
})();
</script>
