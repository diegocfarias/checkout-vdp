@php
    $prefill = $prefill ?? null;
    $compact = $compact ?? false;
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

<form action="{{ route('search.results') }}" method="GET" id="search-form" class="{{ $compact ? 'w-full' : 'max-w-4xl mx-auto' }}">
    <div class="{{ $compact ? 'bg-white rounded-xl border border-gray-200 p-4 sm:p-5' : 'bg-white rounded-2xl shadow-2xl p-5 sm:p-6' }}">
        <div class="flex gap-4 mb-5">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="trip_type" value="roundtrip" {{ $tripType === 'roundtrip' ? 'checked' : '' }}
                       class="w-4 h-4 text-emerald-600 focus:ring-emerald-500" onchange="toggleInbound()">
                <span class="text-sm font-medium text-gray-700">Ida e volta</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="trip_type" value="oneway" {{ $tripType === 'oneway' ? 'checked' : '' }}
                       class="w-4 h-4 text-emerald-600 focus:ring-emerald-500" onchange="toggleInbound()">
                <span class="text-sm font-medium text-gray-700">Somente ida</span>
            </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div class="relative">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Origem</label>
                <input type="text" id="departure-input" placeholder="De onde você sai?"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                       autocomplete="off" required
                       @if($prefill) value="{{ $prefill['departure'] }}" @endif>
                <input type="hidden" name="departure" id="departure-iata" @if($prefill) value="{{ $prefill['departure'] }}" @endif>
                <div id="departure-dropdown" class="absolute z-50 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 hidden max-h-60 overflow-y-auto"></div>
            </div>
            <div class="relative">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Destino</label>
                <input type="text" id="arrival-input" placeholder="Para onde você vai?"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                       autocomplete="off" required
                       @if($prefill) value="{{ $prefill['arrival'] }}" @endif>
                <input type="hidden" name="arrival" id="arrival-iata" @if($prefill) value="{{ $prefill['arrival'] }}" @endif>
                <div id="arrival-dropdown" class="absolute z-50 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 hidden max-h-60 overflow-y-auto"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4" style="overflow:visible;">
            <div class="relative sm:col-span-1" id="datepicker-container" style="overflow:visible;">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Datas</label>
                <button type="button" id="datepicker-toggle"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm text-left bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span id="datepicker-label" class="text-gray-500">Selecione as datas</span>
                </button>
                <input type="hidden" name="outbound_date" id="outbound-date" required
                       @if($prefill && !empty($prefill['outbound_date'])) value="{{ $prefill['outbound_date'] }}" @endif>
                <input type="hidden" name="inbound_date" id="inbound-date"
                       @if($prefill && !empty($prefill['inbound_date'])) value="{{ $prefill['inbound_date'] }}" @endif>

                <div id="datepicker-dropdown" class="hidden absolute z-[100] left-0 mt-2 bg-white border border-gray-200 rounded-2xl shadow-2xl p-4 sm:p-5"
                     style="width: min(calc(100vw - 2rem), 580px);">
                    <div class="flex items-center justify-between mb-4">
                        <button type="button" id="dp-prev" class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <div class="flex gap-8" id="dp-month-titles"></div>
                        <button type="button" id="dp-next" class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                    <div id="dp-calendars" class="flex gap-6 overflow-hidden"></div>
                    <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-400" id="dp-hint">Selecione a data de ida</p>
                        <button type="button" id="dp-clear" class="text-xs text-emerald-600 font-medium hover:text-emerald-700">Limpar</button>
                    </div>
                </div>
            </div>
            <div class="relative">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Passageiros</label>
                <button type="button" id="pax-toggle"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm text-left bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none flex items-center justify-between">
                    <span id="pax-label">1 Adulto</span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div id="pax-dropdown" class="absolute z-50 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 hidden p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div><p class="text-sm font-medium text-gray-700">Adultos</p><p class="text-xs text-gray-400">12+ anos</p></div>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="changePax('adults',-1)" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100">−</button>
                            <span id="pax-adults" class="text-sm font-semibold w-4 text-center">{{ $prefill['adults'] ?? 1 }}</span>
                            <button type="button" onclick="changePax('adults',1)" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100">+</button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div><p class="text-sm font-medium text-gray-700">Crianças</p><p class="text-xs text-gray-400">2-11 anos</p></div>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="changePax('children',-1)" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100">−</button>
                            <span id="pax-children" class="text-sm font-semibold w-4 text-center">{{ $prefill['children'] ?? 0 }}</span>
                            <button type="button" onclick="changePax('children',1)" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100">+</button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div><p class="text-sm font-medium text-gray-700">Bebês</p><p class="text-xs text-gray-400">0-1 ano</p></div>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="changePax('infants',-1)" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100">−</button>
                            <span id="pax-infants" class="text-sm font-semibold w-4 text-center">{{ $prefill['infants'] ?? 0 }}</span>
                            <button type="button" onclick="changePax('infants',1)" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100">+</button>
                        </div>
                    </div>
                    <button type="button" onclick="document.getElementById('pax-dropdown').classList.add('hidden')"
                            class="w-full text-center text-sm text-emerald-600 font-semibold pt-2 border-t border-gray-100">Pronto</button>
                </div>
                <input type="hidden" name="adults" id="input-adults" value="{{ $prefill['adults'] ?? 1 }}">
                <input type="hidden" name="children" id="input-children" value="{{ $prefill['children'] ?? 0 }}">
                <input type="hidden" name="infants" id="input-infants" value="{{ $prefill['infants'] ?? 0 }}">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Classe</label>
                <select name="cabin"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                    <option value="EC" {{ ($prefill['cabin'] ?? 'EC') === 'EC' ? 'selected' : '' }}>Econômica</option>
                    <option value="EX" {{ ($prefill['cabin'] ?? 'EC') === 'EX' ? 'selected' : '' }}>Executiva</option>
                </select>
            </div>
        </div>

        <button type="submit" id="btn-search"
                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3.5 rounded-xl transition-colors text-base flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            Buscar voos
        </button>
    </div>
</form>

<script>
(function() {
    var prefillData = @json($prefill);

    var debounceTimers = {};
    function debounce(id, fn, delay) {
        clearTimeout(debounceTimers[id]);
        debounceTimers[id] = setTimeout(fn, delay);
    }

    function setupAutocomplete(inputId, hiddenId, dropdownId) {
        var input = document.getElementById(inputId);
        var hidden = document.getElementById(hiddenId);
        var dropdown = document.getElementById(dropdownId);
        input.addEventListener('input', function() {
            var term = input.value.trim();
            hidden.value = '';
            if (term.length < 2) { dropdown.classList.add('hidden'); return; }
            debounce(inputId, function() {
                fetch('/api/airports', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ term: term })
                })
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    var data = Array.isArray(response) ? response : (response.airports || response.data || []);
                    if (!Array.isArray(data) || data.length === 0) {
                        dropdown.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400">Nenhum resultado</div>';
                        dropdown.classList.remove('hidden');
                        return;
                    }
                    dropdown.innerHTML = '';
                    data.forEach(function(item) {
                        var iata = item.iata_code || item.iata || item.code || '';
                        var city = item.city || '';
                        var div = document.createElement('div');
                        div.className = 'px-4 py-3 text-sm hover:bg-emerald-50 cursor-pointer border-b border-gray-50 last:border-0';
                        div.innerHTML = '<span class="font-semibold text-gray-800">' + iata + '</span> <span class="text-gray-500">' + city + '</span>';
                        div.addEventListener('click', function() {
                            input.value = iata + ' - ' + city;
                            hidden.value = iata;
                            dropdown.classList.add('hidden');
                        });
                        dropdown.appendChild(div);
                    });
                    dropdown.classList.remove('hidden');
                })
                .catch(function() { dropdown.classList.add('hidden'); });
            }, 300);
        });
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('hidden');
        });
    }
    setupAutocomplete('departure-input', 'departure-iata', 'departure-dropdown');
    setupAutocomplete('arrival-input', 'arrival-iata', 'arrival-dropdown');

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

    window.toggleInbound = function() {
        var rt = document.querySelector('input[name="trip_type"]:checked').value === 'roundtrip';
        if (!rt) {
            dpInbound = null;
            document.getElementById('inbound-date').value = '';
        }
        dpRender();
        dpUpdateLabel();
    };

    // ========== DATEPICKER ==========
    var MONTHS = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    var DAYS = ['D','S','T','Q','Q','S','S'];
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
        if (dpOutbound) {
            dpViewMonth = dpOutbound.getMonth();
            dpViewYear = dpOutbound.getFullYear();
        }
    }
    if (ibVal) {
        dpInbound = parseDate(ibVal);
    }

    function isRoundtrip() {
        return document.querySelector('input[name="trip_type"]:checked').value === 'roundtrip';
    }

    function dateKey(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }

    function formatDisplay(d) {
        return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
    }

    function sameDay(a, b) {
        return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    function isMobile() { return window.innerWidth < 640; }

    function dpRender() {
        var titles = document.getElementById('dp-month-titles');
        var cals = document.getElementById('dp-calendars');
        var count = isMobile() ? 1 : 2;
        titles.innerHTML = '';
        cals.innerHTML = '';
        for (var i = 0; i < count; i++) {
            var m = (dpViewMonth + i) % 12;
            var y = dpViewYear + Math.floor((dpViewMonth + i) / 12);
            var title = document.createElement('span');
            title.className = 'text-sm font-semibold text-gray-800';
            title.textContent = MONTHS[m] + ' ' + y;
            titles.appendChild(title);
            cals.appendChild(buildMonth(y, m));
        }
        var hint = document.getElementById('dp-hint');
        if (!dpOutbound) hint.textContent = 'Selecione a data de ida';
        else if (isRoundtrip() && !dpInbound) hint.textContent = 'Selecione a data de volta';
        else hint.textContent = '';
    }

    function buildMonth(year, month) {
        var wrap = document.createElement('div');
        wrap.className = 'flex-1 min-w-0';
        var header = document.createElement('div');
        header.className = 'grid grid-cols-7 mb-1';
        DAYS.forEach(function(d) {
            var span = document.createElement('span');
            span.className = 'text-center text-[10px] font-semibold text-gray-400 py-1';
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
            empty.className = 'h-9';
            grid.appendChild(empty);
        }

        for (var d = 1; d <= daysInMonth; d++) {
            var date = new Date(year, month, d);
            var cell = document.createElement('button');
            cell.type = 'button';
            cell.textContent = d;
            var isPast = date < today;
            var isStart = sameDay(date, dpOutbound);
            var isEnd = sameDay(date, dpInbound);
            var inRange = false;

            if (dpOutbound && !dpInbound && dpHover && date > dpOutbound && date <= dpHover) {
                inRange = true;
            } else if (dpOutbound && dpInbound && date > dpOutbound && date < dpInbound) {
                inRange = true;
            }

            var cls = 'h-9 text-sm rounded-lg transition-colors ';
            if (isPast) {
                cls += 'text-gray-300 cursor-not-allowed';
            } else if (isStart || isEnd) {
                cls += 'bg-emerald-600 text-white font-semibold';
            } else if (inRange) {
                cls += 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200 cursor-pointer';
            } else {
                cls += 'text-gray-700 hover:bg-gray-100 cursor-pointer';
            }
            cell.className = cls;

            if (!isPast) {
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
                            dpRender();
                        }
                    });
                })(date);
            }
            grid.appendChild(cell);
        }
        wrap.appendChild(grid);
        return wrap;
    }

    function dpSelectDate(date) {
        if (!dpOutbound || (dpOutbound && dpInbound) || !isRoundtrip()) {
            dpOutbound = date;
            dpInbound = null;
            dpHover = null;
        } else {
            if (date < dpOutbound) {
                dpOutbound = date;
                dpInbound = null;
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
        dpRender();

        if (!isRoundtrip() && dpOutbound) {
            setTimeout(function() { dpToggle(false); }, 200);
        }
        if (isRoundtrip() && dpOutbound && dpInbound) {
            setTimeout(function() { dpToggle(false); }, 200);
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

    function dpToggle(force) {
        var dd = document.getElementById('datepicker-dropdown');
        dpOpen = force !== undefined ? force : !dpOpen;
        dd.classList.toggle('hidden', !dpOpen);
        if (dpOpen) dpRender();
    }

    document.getElementById('datepicker-toggle').addEventListener('click', function(e) {
        e.stopPropagation();
        dpToggle();
    });
    document.getElementById('dp-prev').addEventListener('click', function(e) {
        e.stopPropagation();
        dpViewMonth--;
        if (dpViewMonth < 0) { dpViewMonth = 11; dpViewYear--; }
        dpRender();
    });
    document.getElementById('dp-next').addEventListener('click', function(e) {
        e.stopPropagation();
        dpViewMonth++;
        if (dpViewMonth > 11) { dpViewMonth = 0; dpViewYear++; }
        dpRender();
    });
    document.getElementById('dp-clear').addEventListener('click', function(e) {
        e.stopPropagation();
        dpOutbound = null; dpInbound = null; dpHover = null;
        document.getElementById('outbound-date').value = '';
        document.getElementById('inbound-date').value = '';
        dpUpdateLabel();
        dpRender();
    });
    document.addEventListener('click', function(e) {
        var container = document.getElementById('datepicker-container');
        if (dpOpen && !container.contains(e.target)) dpToggle(false);
    });

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
                cabin: document.querySelector('#search-form select[name="cabin"]').value,
                trip_type: document.querySelector('#search-form input[name="trip_type"]:checked').value
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
                    var radio = document.querySelector('#search-form input[name="trip_type"][value="' + saved.trip_type + '"]');
                    if (radio) radio.checked = true;
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
                    document.querySelector('#search-form select[name="cabin"]').value = saved.cabin;
                }
            }
        } catch(ex) {}
    }
})();
</script>
