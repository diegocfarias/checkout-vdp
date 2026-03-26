@extends('layouts.public')

@section('title', 'Resultados - Voos')

@section('container_class', 'max-w-6xl')

@section('content')
<div class="space-y-4 pb-8">
    {{-- Resumo da busca --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
            <span class="font-semibold text-gray-800">{{ $params['departure'] }}</span>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            <span class="font-semibold text-gray-800">{{ $params['arrival'] }}</span>
            @if($isRoundtrip)
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span class="font-semibold text-gray-800">{{ $params['departure'] }}</span>
            @endif
            <span class="text-gray-400">|</span>
            <span>{{ \Carbon\Carbon::parse($params['outbound_date'])->format('d/m/Y') }}</span>
            @if(!empty($params['inbound_date']))
                <span>- {{ \Carbon\Carbon::parse($params['inbound_date'])->format('d/m/Y') }}</span>
            @endif
            <span class="text-gray-400">|</span>
            <span>{{ $params['adults'] }} ad.{{ $params['children'] > 0 ? ', ' . $params['children'] . ' cr.' : '' }}{{ $params['infants'] > 0 ? ', ' . $params['infants'] . ' bb.' : '' }}</span>
            <span class="text-gray-400">|</span>
            <span>{{ $params['cabin'] === 'EX' ? 'Executiva' : 'Econômica' }}</span>
            <a href="{{ route('search.home') }}" class="ml-auto text-emerald-600 hover:text-emerald-700 font-medium text-sm">Nova busca</a>
        </div>
    </div>

    @if(count($groups) === 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h2 class="text-xl font-semibold text-gray-700 mb-2">Nenhum voo encontrado</h2>
            <p class="text-gray-500 mb-6">Tente alterar as datas ou os aeroportos da sua busca.</p>
            <a href="{{ route('search.home') }}" class="inline-block bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors">Nova busca</a>
        </div>
    @else
        <div class="flex flex-col lg:flex-row gap-5">
            {{-- Sidebar Filtros (desktop) --}}
            <aside class="hidden lg:block w-64 shrink-0">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sticky top-4 space-y-5">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-gray-800 text-sm">Filtros</h3>
                        <button type="button" onclick="clearFilters()" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Limpar</button>
                    </div>

                    @if(count($airlines) > 0)
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Companhia</p>
                        @foreach($airlines as $cia)
                        <label class="flex items-center gap-2 py-1 cursor-pointer">
                            <input type="checkbox" class="filter-cia w-3.5 h-3.5 text-emerald-600 rounded focus:ring-emerald-500" value="{{ strtolower($cia) }}" checked>
                            <span class="text-sm text-gray-700">{{ strtoupper($cia) }}</span>
                        </label>
                        @endforeach
                    </div>
                    @endif

                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Paradas</p>
                        <label class="flex items-center gap-2 py-1 cursor-pointer">
                            <input type="checkbox" class="filter-stops w-3.5 h-3.5 text-emerald-600 rounded focus:ring-emerald-500" value="direct" checked>
                            <span class="text-sm text-gray-700">Direto</span>
                        </label>
                        <label class="flex items-center gap-2 py-1 cursor-pointer">
                            <input type="checkbox" class="filter-stops w-3.5 h-3.5 text-emerald-600 rounded focus:ring-emerald-500" value="connection" checked>
                            <span class="text-sm text-gray-700">Com conexão</span>
                        </label>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Horário ida</p>
                        @foreach(['madrugada' => 'Madrugada (00-06)', 'manha' => 'Manhã (06-12)', 'tarde' => 'Tarde (12-18)', 'noite' => 'Noite (18-00)'] as $key => $label)
                        <label class="flex items-center gap-2 py-1 cursor-pointer">
                            <input type="checkbox" class="filter-ob-period w-3.5 h-3.5 text-emerald-600 rounded focus:ring-emerald-500" value="{{ $key }}" checked>
                            <span class="text-sm text-gray-700">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>

                    @if($isRoundtrip)
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Horário volta</p>
                        @foreach(['madrugada' => 'Madrugada (00-06)', 'manha' => 'Manhã (06-12)', 'tarde' => 'Tarde (12-18)', 'noite' => 'Noite (18-00)'] as $key => $label)
                        <label class="flex items-center gap-2 py-1 cursor-pointer">
                            <input type="checkbox" class="filter-ib-period w-3.5 h-3.5 text-emerald-600 rounded focus:ring-emerald-500" value="{{ $key }}" checked>
                            <span class="text-sm text-gray-700">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                    @endif
                </div>
            </aside>

            {{-- Conteudo principal --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <div class="flex bg-white rounded-lg border border-gray-200 overflow-hidden text-sm">
                        <button type="button" data-sort="price" class="sort-tab px-4 py-2 font-medium text-white bg-emerald-600">Menor preço</button>
                        <button type="button" data-sort="same-cia" class="sort-tab px-4 py-2 font-medium text-gray-600 hover:bg-gray-50">Mesma cia</button>
                    </div>
                    <span class="text-sm text-gray-400 ml-auto" id="results-count">{{ count($groups) }} resultado{{ count($groups) !== 1 ? 's' : '' }}</span>

                    <button type="button" onclick="toggleMobileFilters()" class="lg:hidden flex items-center gap-1.5 bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filtros
                    </button>
                </div>

                <div class="space-y-3" id="combinations-list">
                    @foreach($groups as $groupIdx => $group)
                        @include('search._combination_card', ['group' => $group, 'groupIdx' => $groupIdx, 'searchId' => $search->id])
                    @endforeach
                </div>

                <div id="no-results-msg" class="hidden bg-white rounded-xl border border-gray-200 p-8 text-center">
                    <p class="text-gray-500">Nenhum resultado com os filtros selecionados.</p>
                    <button type="button" onclick="clearFilters()" class="mt-3 text-emerald-600 font-medium text-sm">Limpar filtros</button>
                </div>

                <div id="load-more-wrap" class="text-center mt-4">
                    <button type="button" id="load-more-btn" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:border-gray-300 text-gray-700 font-medium text-sm px-6 py-2.5 rounded-lg transition-colors">
                        Carregar mais resultados
                    </button>
                </div>
            </div>
        </div>

        {{-- Modal filtros mobile --}}
        <div id="mobile-filters" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-black/40" onclick="toggleMobileFilters()"></div>
            <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-2xl max-h-[80vh] overflow-y-auto p-5 space-y-5">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-gray-800">Filtros</h3>
                    <button type="button" onclick="toggleMobileFilters()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                @if(count($airlines) > 0)
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Companhia</p>
                    @foreach($airlines as $cia)
                    <label class="flex items-center gap-2 py-1 cursor-pointer">
                        <input type="checkbox" class="filter-cia w-3.5 h-3.5 text-emerald-600 rounded focus:ring-emerald-500" value="{{ strtolower($cia) }}" checked>
                        <span class="text-sm text-gray-700">{{ strtoupper($cia) }}</span>
                    </label>
                    @endforeach
                </div>
                @endif

                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Paradas</p>
                    <label class="flex items-center gap-2 py-1 cursor-pointer">
                        <input type="checkbox" class="filter-stops w-3.5 h-3.5 text-emerald-600 rounded focus:ring-emerald-500" value="direct" checked>
                        <span class="text-sm text-gray-700">Direto</span>
                    </label>
                    <label class="flex items-center gap-2 py-1 cursor-pointer">
                        <input type="checkbox" class="filter-stops w-3.5 h-3.5 text-emerald-600 rounded focus:ring-emerald-500" value="connection" checked>
                        <span class="text-sm text-gray-700">Com conexão</span>
                    </label>
                </div>

                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Horário ida</p>
                    @foreach(['madrugada' => 'Madrugada (00-06)', 'manha' => 'Manhã (06-12)', 'tarde' => 'Tarde (12-18)', 'noite' => 'Noite (18-00)'] as $key => $label)
                    <label class="flex items-center gap-2 py-1 cursor-pointer">
                        <input type="checkbox" class="filter-ob-period w-3.5 h-3.5 text-emerald-600 rounded focus:ring-emerald-500" value="{{ $key }}" checked>
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>

                @if($isRoundtrip)
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Horário volta</p>
                    @foreach(['madrugada' => 'Madrugada (00-06)', 'manha' => 'Manhã (06-12)', 'tarde' => 'Tarde (12-18)', 'noite' => 'Noite (18-00)'] as $key => $label)
                    <label class="flex items-center gap-2 py-1 cursor-pointer">
                        <input type="checkbox" class="filter-ib-period w-3.5 h-3.5 text-emerald-600 rounded focus:ring-emerald-500" value="{{ $key }}" checked>
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
                @endif

                <div class="flex gap-3 pt-2 border-t border-gray-100">
                    <button type="button" onclick="clearFilters()" class="flex-1 py-2.5 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg">Limpar</button>
                    <button type="button" onclick="toggleMobileFilters()" class="flex-1 py-2.5 text-sm font-semibold text-white bg-emerald-600 rounded-lg">Aplicar</button>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function() {
    var isRoundtrip = @json($isRoundtrip);
    var groupsData = @json($groups);
    var PAGE_SIZE = 10;
    var currentPage = 1;

    // --- Flight selection within groups ---
    function updateFlightSelection(groupIdx, dir, flightIdx) {
        var form = document.querySelector('.group-form[data-group="' + groupIdx + '"]');
        if (!form) return;
        var flights = dir === 'ob'
            ? groupsData[groupIdx].outbound_flights
            : groupsData[groupIdx].inbound_flights;
        var flight = flights[flightIdx];
        if (!flight) return;
        var input = form.querySelector(dir === 'ob' ? '.selected-ob' : '.selected-ib');
        if (input) input.value = JSON.stringify(flight);
    }

    document.querySelectorAll('.flight-option').forEach(function(label) {
        label.addEventListener('click', function() {
            var groupIdx = parseInt(label.dataset.group);
            var dir = label.dataset.dir;
            var radio = label.querySelector('input[type="radio"]');
            if (!radio) return;
            updateFlightSelection(groupIdx, dir, parseInt(radio.value));

            var activeClasses = dir === 'ob' ? ['border-emerald-400', 'bg-emerald-50'] : ['border-blue-400', 'bg-blue-50'];
            var dotBorder = dir === 'ob' ? 'border-emerald-600' : 'border-blue-600';
            var dotBg = dir === 'ob' ? 'bg-emerald-600' : 'bg-blue-600';

            label.closest('.space-y-1').querySelectorAll('.flight-option[data-dir="' + dir + '"]').forEach(function(opt) {
                opt.classList.remove('border-emerald-400', 'bg-emerald-50', 'border-blue-400', 'bg-blue-50');
                opt.classList.add('border-gray-200');
                var d = opt.querySelector('.radio-dot');
                if (d) { d.classList.remove('border-emerald-600', 'border-blue-600'); d.classList.add('border-gray-300'); }
                var inner = opt.querySelector('.radio-dot-inner');
                if (inner) { inner.classList.remove('bg-emerald-600', 'bg-blue-600'); }
            });

            label.classList.remove('border-gray-200');
            activeClasses.forEach(function(c) { label.classList.add(c); });
            var d = label.querySelector('.radio-dot');
            if (d) { d.classList.remove('border-gray-300'); d.classList.add(dotBorder); }
            var inner = label.querySelector('.radio-dot-inner');
            if (inner) { inner.classList.add(dotBg); }
        });
    });

    // --- Filters ---
    function getCheckedValues(sel) {
        var v = [];
        document.querySelectorAll(sel + ':checked').forEach(function(el) { v.push(el.value); });
        return v;
    }

    function applyFilters() {
        var ciaVals = getCheckedValues('.filter-cia');
        var stopVals = getCheckedValues('.filter-stops');
        var obPeriodVals = getCheckedValues('.filter-ob-period');
        var ibPeriodVals = getCheckedValues('.filter-ib-period');

        var total = 0;
        document.querySelectorAll('.combination-card').forEach(function(card) {
            var cardAirlines = card.dataset.airlines.split(',');
            var hasDirect = card.dataset.hasDirect === '1';
            var hasConn = card.dataset.hasConnection === '1';
            var obPeriods = card.dataset.outboundPeriod ? card.dataset.outboundPeriod.split(',') : [];
            var ibPeriods = card.dataset.inboundPeriod ? card.dataset.inboundPeriod.split(',') : [];

            var ciaOk = ciaVals.length === 0 || cardAirlines.some(function(a) { return ciaVals.includes(a); });

            var stopOk = stopVals.length === 0 ||
                (hasDirect && stopVals.includes('direct')) ||
                (hasConn && stopVals.includes('connection'));

            var obOk = obPeriodVals.length === 0 || obPeriods.some(function(p) { return obPeriodVals.includes(p); });
            var ibOk = !isRoundtrip || ibPeriodVals.length === 0 || ibPeriods.some(function(p) { return ibPeriodVals.includes(p); });

            var pass = ciaOk && stopOk && obOk && ibOk;
            card.dataset.filtered = pass ? '1' : '0';
            if (pass) total++;
        });

        currentPage = 1;
        paginate();
        document.getElementById('results-count').textContent = total + ' resultado' + (total !== 1 ? 's' : '');
        document.getElementById('no-results-msg').classList.toggle('hidden', total > 0);
    }

    // --- Pagination ---
    function paginate() {
        var cards = Array.from(document.querySelectorAll('.combination-card'));
        var shown = 0;
        var maxShow = currentPage * PAGE_SIZE;
        var totalFiltered = 0;

        cards.forEach(function(card) {
            if (card.dataset.filtered === '0') {
                card.style.display = 'none';
                return;
            }
            totalFiltered++;
            if (totalFiltered <= maxShow) {
                card.style.display = '';
                shown++;
            } else {
                card.style.display = 'none';
            }
        });

        var btn = document.getElementById('load-more-btn');
        var wrap = document.getElementById('load-more-wrap');
        if (shown >= totalFiltered || totalFiltered === 0) {
            wrap.style.display = 'none';
        } else {
            wrap.style.display = '';
            btn.textContent = 'Carregar mais resultados (' + (totalFiltered - shown) + ' restantes)';
        }
    }

    document.getElementById('load-more-btn').addEventListener('click', function() {
        currentPage++;
        paginate();
    });

    // Init: mark all as filtered=1
    document.querySelectorAll('.combination-card').forEach(function(c) { c.dataset.filtered = '1'; });
    paginate();

    document.querySelectorAll('.filter-cia, .filter-stops, .filter-ob-period, .filter-ib-period').forEach(function(el) {
        el.addEventListener('change', applyFilters);
    });

    window.clearFilters = function() {
        document.querySelectorAll('.filter-cia, .filter-stops, .filter-ob-period, .filter-ib-period').forEach(function(el) {
            el.checked = true;
        });
        applyFilters();
    };

    // --- Sorting ---
    document.querySelectorAll('.sort-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.sort-tab').forEach(function(t) {
                t.classList.remove('text-white', 'bg-emerald-600');
                t.classList.add('text-gray-600');
            });
            tab.classList.remove('text-gray-600');
            tab.classList.add('text-white', 'bg-emerald-600');

            var sortBy = tab.dataset.sort;
            var list = document.getElementById('combinations-list');
            var cards = Array.from(list.querySelectorAll('.combination-card'));

            cards.sort(function(a, b) {
                if (sortBy === 'price') {
                    return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                }
                if (sortBy === 'same-cia') {
                    var aSame = a.dataset.sameCia === '1' ? 0 : 1;
                    var bSame = b.dataset.sameCia === '1' ? 0 : 1;
                    if (aSame !== bSame) return aSame - bSame;
                    return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                }
                return 0;
            });

            cards.forEach(function(card) { list.appendChild(card); });
            currentPage = 1;
            paginate();
        });
    });

    // --- Toggle more flights ---
    document.querySelectorAll('.toggle-more-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = btn.dataset.target;
            var count = btn.dataset.count;
            var items = document.querySelectorAll('.' + target);
            var isHidden = items[0] && items[0].classList.contains('hidden');

            items.forEach(function(el) { el.classList.toggle('hidden'); });

            if (isHidden) {
                btn.textContent = 'Ver menos';
            } else {
                var dir = target.includes('-ob-') ? 'ida' : 'volta';
                btn.textContent = '+ ' + count + ' opções de ' + dir;
            }
        });
    });

    // --- Toggle connection details ---
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.conn-toggle-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var target = document.getElementById(btn.dataset.target);
        if (!target) return;
        var isHidden = target.classList.contains('hidden');
        target.classList.toggle('hidden');
        var text = btn.textContent.trim();
        if (isHidden) {
            btn.textContent = text.replace('▾', '▴');
        } else {
            btn.textContent = text.replace('▴', '▾');
        }
    });

    // --- Flight selection loading ---
    document.querySelectorAll('.group-form').forEach(function(form) {
        form.addEventListener('submit', function() {
            showTravelLoading({
                title: 'Carregando detalhes do voo...',
                messages: [
                    'Verificando disponibilidade...',
                    'Consultando preço atualizado...',
                    'Preparando seu checkout...'
                ],
                timeoutMs: 45000
            });
        });
    });

    // --- Mobile filters ---
    window.toggleMobileFilters = function() {
        document.getElementById('mobile-filters').classList.toggle('hidden');
        document.body.classList.toggle('overflow-hidden');
    };
})();
</script>
@endpush
