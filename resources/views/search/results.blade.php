@extends('layouts.public')

@section('title', 'Resultados - Voos')

@section('container_class', 'max-w-6xl')

@section('content')
<div class="space-y-6 pb-8">
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium">
            {{ session('error') }}
        </div>
    @endif

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
            <button type="button" id="toggle-search-form" class="ml-auto text-blue-600 hover:text-blue-700 font-medium text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Nova busca
            </button>
        </div>
    </div>

    <div id="inline-search-form" class="hidden">
        @include('search._search_form', ['prefill' => $params, 'compact' => true])
    </div>

    {{-- Progress bar --}}
    <div id="search-progress" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex items-center gap-3 mb-3">
            <div class="relative w-5 h-5" id="progress-spinner">
                <svg class="animate-spin w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
            <span id="progress-text" class="text-sm font-medium text-gray-700">Buscando os melhores voos...</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
            <div id="progress-fill" class="bg-blue-600 h-2 rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-5">
        {{-- Sidebar Filtros (desktop) --}}
        <aside id="filters-sidebar" class="hidden lg:block w-64 shrink-0">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 sticky top-[72px] overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        <h3 class="font-semibold text-gray-800 text-sm">Filtros</h3>
                        <span id="active-filter-count" class="hidden text-xs font-bold text-white bg-blue-500 rounded-full w-4.5 h-4.5 flex items-center justify-center leading-none px-1.5 py-0.5">0</span>
                    </div>
                    <button type="button" onclick="clearFilters()" class="text-xs text-blue-600 hover:text-blue-700 font-medium transition-colors">Limpar</button>
                </div>

                <div id="desktop-cia-filter" class="px-5 py-4 border-b border-gray-100 hidden">
                    <div class="flex items-center gap-1.5 mb-3">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Companhia</p>
                    </div>
                    <div class="flex flex-wrap gap-1.5" id="desktop-cia-pills"></div>
                </div>

                <div class="px-5 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-1.5 mb-3">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Paradas</p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <label class="filter-pill cursor-pointer">
                            <input type="checkbox" class="filter-stops sr-only" value="direct">
                            <span class="filter-pill-label inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border transition-all">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                Direto
                            </span>
                        </label>
                        <label class="filter-pill cursor-pointer">
                            <input type="checkbox" class="filter-stops sr-only" value="connection">
                            <span class="filter-pill-label inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border transition-all">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h7m4 0h7"/></svg>
                                Conexão
                            </span>
                        </label>
                    </div>
                </div>

                <div class="px-5 py-4 {{ $isRoundtrip ? 'border-b border-gray-100' : '' }}">
                    <div class="flex items-center gap-1.5 mb-3">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Horário ida</p>
                    </div>
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach(['madrugada' => '00h-06h', 'manha' => '06h-12h', 'tarde' => '12h-18h', 'noite' => '18h-00h'] as $key => $label)
                        <label class="filter-pill cursor-pointer">
                            <input type="checkbox" class="filter-ob-period sr-only" value="{{ $key }}">
                            <span class="filter-pill-label inline-flex flex-col items-center gap-0.5 px-2 py-2 rounded-xl text-xs font-medium border transition-all w-full text-center">
                                {{ $label }}
                            </span>
                        </label>
                        @endforeach
                    </div>
                </div>

                @if($isRoundtrip)
                <div class="px-5 py-4">
                    <div class="flex items-center gap-1.5 mb-3">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Horário volta</p>
                    </div>
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach(['madrugada' => '00h-06h', 'manha' => '06h-12h', 'tarde' => '12h-18h', 'noite' => '18h-00h'] as $key => $label)
                        <label class="filter-pill cursor-pointer">
                            <input type="checkbox" class="filter-ib-period sr-only" value="{{ $key }}">
                            <span class="filter-pill-label inline-flex flex-col items-center gap-0.5 px-2 py-2 rounded-xl text-xs font-medium border transition-all w-full text-center">
                                {{ $label }}
                            </span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </aside>

        {{-- Conteudo principal --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3 mb-5">
                <div class="flex bg-white rounded-lg border border-gray-200 overflow-hidden text-sm shadow-sm">
                    <button type="button" data-sort="price" class="sort-tab sort-tab-active px-5 py-2.5 font-medium text-white bg-blue-600">Menor preço</button>
                    @if($mixEnabled)
                    <button type="button" data-sort="same-cia" class="sort-tab px-5 py-2.5 font-medium text-gray-600 hover:bg-gray-50">Mesma cia</button>
                    @endif
                </div>
                <span class="text-sm text-gray-500 ml-auto font-medium" id="results-count">Buscando...</span>
            </div>

            <div class="space-y-5" id="combinations-list">
                {{-- Skeleton cards --}}
                @for($i = 0; $i < 3; $i++)
                <div class="skeleton-card bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="animate-pulse">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-4 bg-gray-200 rounded w-16"></div>
                            <div class="h-3 bg-gray-200 rounded w-24"></div>
                        </div>
                        <div class="flex items-center gap-4 mb-3">
                            <div class="text-center">
                                <div class="h-6 bg-gray-200 rounded w-14 mb-1"></div>
                                <div class="h-3 bg-gray-200 rounded w-10"></div>
                            </div>
                            <div class="flex-1 flex flex-col items-center">
                                <div class="h-3 bg-gray-200 rounded w-12 mb-1"></div>
                                <div class="w-full h-px bg-gray-200"></div>
                                <div class="h-3 bg-gray-200 rounded w-10 mt-1"></div>
                            </div>
                            <div class="text-center">
                                <div class="h-6 bg-gray-200 rounded w-14 mb-1"></div>
                                <div class="h-3 bg-gray-200 rounded w-10"></div>
                            </div>
                        </div>
                        @if($isRoundtrip)
                        <div class="border-t border-gray-100 pt-3 mt-3">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="h-4 bg-gray-200 rounded w-16"></div>
                                <div class="h-3 bg-gray-200 rounded w-24"></div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-center">
                                    <div class="h-6 bg-gray-200 rounded w-14 mb-1"></div>
                                    <div class="h-3 bg-gray-200 rounded w-10"></div>
                                </div>
                                <div class="flex-1 flex flex-col items-center">
                                    <div class="h-3 bg-gray-200 rounded w-12 mb-1"></div>
                                    <div class="w-full h-px bg-gray-200"></div>
                                    <div class="h-3 bg-gray-200 rounded w-10 mt-1"></div>
                                </div>
                                <div class="text-center">
                                    <div class="h-6 bg-gray-200 rounded w-14 mb-1"></div>
                                    <div class="h-3 bg-gray-200 rounded w-10"></div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endfor
            </div>

            <div id="no-results-msg" class="hidden bg-white rounded-xl border border-gray-200 p-8 text-center">
                <p class="text-gray-500">Nenhum resultado com os filtros selecionados.</p>
                <button type="button" onclick="clearFilters()" class="mt-3 text-blue-600 font-medium text-sm">Limpar filtros</button>
            </div>

            <div id="load-more-wrap" class="text-center mt-4" style="display:none">
                <button type="button" id="load-more-btn" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:border-gray-300 text-gray-700 font-medium text-sm px-6 py-2.5 rounded-lg transition-colors">
                    Carregar mais resultados
                </button>
            </div>
        </div>
    </div>

    {{-- FAB filtros mobile --}}
    <button type="button" onclick="toggleMobileFilters()" class="lg:hidden fixed bottom-6 right-6 z-30 bg-blue-600 hover:bg-blue-700 text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg transition-all active:scale-95" aria-label="Filtros">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
        <span id="mobile-btn-filter-count" class="hidden absolute -top-1 -right-1 text-[10px] font-bold text-white bg-red-500 rounded-full w-5 h-5 flex items-center justify-center leading-none">0</span>
    </button>

    {{-- Modal filtros mobile --}}
    <div id="mobile-filters" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="toggleMobileFilters()"></div>
        <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-2xl max-h-[85vh] flex flex-col shadow-2xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <h3 class="font-bold text-gray-800 text-base">Filtros</h3>
                    <span id="mobile-active-filter-count" class="hidden text-xs font-bold text-white bg-blue-500 rounded-full px-1.5 py-0.5 leading-none">0</span>
                </div>
                <button type="button" onclick="toggleMobileFilters()" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 px-5 py-4 space-y-5">
                <div id="mobile-cia-filter" class="hidden">
                    <div class="flex items-center gap-1.5 mb-3">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Companhia</p>
                    </div>
                    <div class="flex flex-wrap gap-2" id="mobile-cia-pills"></div>
                </div>

                <div>
                    <div class="flex items-center gap-1.5 mb-3">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Paradas</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <label class="filter-pill cursor-pointer">
                            <input type="checkbox" class="filter-stops sr-only" value="direct">
                            <span class="filter-pill-label inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium border transition-all">Direto</span>
                        </label>
                        <label class="filter-pill cursor-pointer">
                            <input type="checkbox" class="filter-stops sr-only" value="connection">
                            <span class="filter-pill-label inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium border transition-all">Conexão</span>
                        </label>
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-1.5 mb-3">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Horário ida</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['madrugada' => '00h-06h', 'manha' => '06h-12h', 'tarde' => '12h-18h', 'noite' => '18h-00h'] as $key => $label)
                        <label class="filter-pill cursor-pointer">
                            <input type="checkbox" class="filter-ob-period sr-only" value="{{ $key }}">
                            <span class="filter-pill-label inline-flex flex-col items-center gap-1 px-3 py-2.5 rounded-xl text-xs font-medium border transition-all w-full text-center">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                @if($isRoundtrip)
                <div>
                    <div class="flex items-center gap-1.5 mb-3">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Horário volta</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['madrugada' => '00h-06h', 'manha' => '06h-12h', 'tarde' => '12h-18h', 'noite' => '18h-00h'] as $key => $label)
                        <label class="filter-pill cursor-pointer">
                            <input type="checkbox" class="filter-ib-period sr-only" value="{{ $key }}">
                            <span class="filter-pill-label inline-flex flex-col items-center gap-1 px-3 py-2.5 rounded-xl text-xs font-medium border transition-all w-full text-center">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <div class="flex gap-3 px-5 py-4 border-t border-gray-100 shrink-0 bg-white">
                <button type="button" onclick="clearFilters()" class="flex-1 py-2.5 text-sm font-medium text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">Limpar</button>
                <button type="button" onclick="toggleMobileFilters()" class="flex-1 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors">Aplicar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .filter-pill input:checked + .filter-pill-label {
        background-color: #eff6ff;
        border-color: #93c5fd;
        color: #1d4ed8;
    }
    .filter-pill input:not(:checked) + .filter-pill-label {
        background-color: #f9fafb;
        border-color: #e5e7eb;
        color: #6b7280;
    }
    .filter-pill input:not(:checked) + .filter-pill-label:hover {
        background-color: #f3f4f6;
        border-color: #d1d5db;
    }
    .filter-pill input:checked + .filter-pill-label svg {
        color: #2563eb;
    }
    .filter-pill input:not(:checked) + .filter-pill-label svg {
        color: #9ca3af;
    }
    .sort-tab-active {
        background-color: #2563eb !important;
        color: #fff !important;
    }
    .sort-tab-active:hover {
        background-color: #2563eb !important;
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeInUp {
        animation: fadeInUp 0.3s ease-out;
    }
</style>
@endpush

@push('scripts')
<script>
(function() {
    var CONFIG = {
        searchId: @json($search->id),
        params: @json($params),
        providerSlots: @json($providerSlots),
        isRoundtrip: @json($isRoundtrip),
        mixEnabled: @json((bool) $mixEnabled),
        pixDiscount: @json((float) $pixDiscount),
        pixEnabled: @json((bool) $pixEnabled),
        maxInstallments: @json($maxInstallments),
        selectUrl: @json(route('search.select')),
        csrfToken: @json(csrf_token()),
        obDateFormatted: @json(isset($params['outbound_date']) ? \Carbon\Carbon::parse($params['outbound_date'])->translatedFormat('D, d/m/Y') : ''),
        ibDateFormatted: @json(!empty($params['inbound_date']) ? \Carbon\Carbon::parse($params['inbound_date'])->translatedFormat('D, d/m/Y') : ''),
        totalPax: {{ ($params['adults'] ?? 1) + ($params['children'] ?? 0) }}
    };

    var allOutbound = [];
    var allInbound = [];
    var patriaOutbound = [];
    var patriaInbound = [];
    var groupsData = [];
    var providerStatus = {};
    var currentPage = 1;
    var PAGE_SIZE = 10;
    var currentSort = 'price';
    var firstResultsRendered = false;
    var renderTimer = null;
    var knownGroupKeys = {};

    // --- Toggle inline search form ---
    var toggleBtn = document.getElementById('toggle-search-form');
    var inlineForm = document.getElementById('inline-search-form');
    if (toggleBtn && inlineForm) {
        toggleBtn.addEventListener('click', function() {
            var isHidden = inlineForm.classList.contains('hidden');
            inlineForm.classList.toggle('hidden');
            toggleBtn.textContent = isHidden ? 'Fechar busca' : 'Nova busca';
            if (isHidden) inlineForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

    // ========================================
    // FORMATTING HELPERS
    // ========================================
    function formatBRL(v) {
        return v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function parseTaxValue(val) {
        if (!val) return 0;
        val = String(val).trim();
        if (val === '') return 0;
        if (val.indexOf(',') !== -1) return parseFloat(val.replace(/\./g, '').replace(',', '.'));
        if (/\.\d{3}$/.test(val)) return parseFloat(val.replace(/\./g, ''));
        return parseFloat(val) || 0;
    }

    function getTimePeriod(time) {
        var h = parseInt((time || '').substring(0, 2), 10) || 0;
        if (h < 6) return 'madrugada';
        if (h < 12) return 'manha';
        if (h < 18) return 'tarde';
        return 'noite';
    }

    function flightHasDirect(flights) {
        for (var i = 0; i < flights.length; i++) {
            var c = flights[i].connection;
            if (!c || !Array.isArray(c) || c.length <= 1) return true;
        }
        return false;
    }

    function flightHasConnection(flights) {
        for (var i = 0; i < flights.length; i++) {
            var c = flights[i].connection;
            if (Array.isArray(c) && c.length > 1) return true;
        }
        return false;
    }

    function groupPeriods(flights) {
        var p = [];
        flights.forEach(function(f) {
            var period = getTimePeriod(f.departure_time || '');
            if (p.indexOf(period) === -1) p.push(period);
        });
        return p;
    }

    function escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function displayCia(operator, flightNumber) {
        var op = (operator || '').toUpperCase().trim();
        if (op !== 'PATRIA') return op;
        var fn = (flightNumber || '').toUpperCase();
        if (fn.indexOf('G3') === 0) return 'GOL';
        if (fn.indexOf('AD') === 0) return 'AZUL';
        if (fn.indexOf('LA') === 0 || fn.indexOf('JJ') === 0) return 'LATAM';
        return op;
    }

    // ========================================
    // DEDUP (multi-provider: keep cheapest)
    // ========================================
    function deduplicateFlights(flights) {
        var best = {};
        flights.forEach(function(f) {
            var price = Number(f.calculated_price);
            if (!Number.isFinite(price) || price <= 0) return;

            var key = (f.flight_number || '') + '|' + (f.departure_time || '');
            if (!best[key] || price < Number(best[key].calculated_price)) {
                best[key] = f;
            }
        });
        return Object.values(best);
    }

    // ========================================
    // PATRIA MERGE
    // ========================================
    function mergeWithPatria(regular, patria) {
        if (!patria.length) return regular;
        var idx = {};
        patria.forEach(function(pf) {
            var key = (pf.flight_number || '') + '|' + (pf.departure_time || '');
            if (!idx[key] || pf.calculated_price < idx[key].calculated_price) idx[key] = pf;
        });
        var used = {};
        var merged = [];
        regular.forEach(function(rf) {
            var key = (rf.flight_number || '') + '|' + (rf.departure_time || '');
            if (idx[key]) {
                merged.push(idx[key].calculated_price < rf.calculated_price ? idx[key] : rf);
                used[key] = true;
            } else {
                merged.push(rf);
            }
        });
        Object.keys(idx).forEach(function(key) {
            if (!used[key]) merged.push(idx[key]);
        });
        return merged;
    }

    // ========================================
    // BUILD GROUPS (port of PHP buildGroups)
    // ========================================
    function buildGroups(outbound, inbound, requireInbound) {
        if (requireInbound && !inbound.length) {
            return [];
        }

        var obByCiaPrice = {};
        outbound.forEach(function(ob) {
            var cia = displayCia(ob.operator, ob.flight_number);
            var price = Number(ob.calculated_price);
            if (!Number.isFinite(price) || price <= 0) return;

            var key = cia + '|' + price.toFixed(2);
            if (!obByCiaPrice[key]) obByCiaPrice[key] = { cia: cia, price: price, flights: [] };
            obByCiaPrice[key].flights.push(ob);
        });

        if (!inbound.length) {
            var groups = [];
            Object.keys(obByCiaPrice).forEach(function(k) {
                var g = obByCiaPrice[k];
                groups.push({
                    airlines: [g.cia],
                    same_cia: true,
                    total_price: g.price,
                    outbound_flights: g.flights,
                    inbound_flights: [],
                    direct: flightHasDirect(g.flights),
                    outbound_periods: groupPeriods(g.flights),
                    inbound_periods: []
                });
            });
            groups.sort(function(a, b) { return a.total_price - b.total_price; });
            return groups;
        }

        var ibByCiaPrice = {};
        inbound.forEach(function(ib) {
            var cia = displayCia(ib.operator, ib.flight_number);
            var price = Number(ib.calculated_price);
            if (!Number.isFinite(price) || price <= 0) return;

            var key = cia + '|' + price.toFixed(2);
            if (!ibByCiaPrice[key]) ibByCiaPrice[key] = { cia: cia, price: price, flights: [] };
            ibByCiaPrice[key].flights.push(ib);
        });

        var groups = [];
        Object.keys(obByCiaPrice).forEach(function(ok) {
            var obG = obByCiaPrice[ok];
            Object.keys(ibByCiaPrice).forEach(function(ik) {
                var ibG = ibByCiaPrice[ik];
                var airlines = [obG.cia];
                if (ibG.cia !== obG.cia) airlines.push(ibG.cia);
                groups.push({
                    airlines: airlines,
                    same_cia: obG.cia === ibG.cia,
                    total_price: Math.round((obG.price + ibG.price) * 100) / 100,
                    outbound_flights: obG.flights,
                    inbound_flights: ibG.flights,
                    direct: flightHasDirect(obG.flights) && flightHasDirect(ibG.flights),
                    outbound_periods: groupPeriods(obG.flights),
                    inbound_periods: groupPeriods(ibG.flights)
                });
            });
        });

        if (!CONFIG.mixEnabled) {
            groups = groups.filter(function(g) { return g.same_cia; });
        }

        groups.sort(function(a, b) { return a.total_price - b.total_price; });
        return groups.slice(0, 200);
    }

    // ========================================
    // CARD RENDERING
    // ========================================
    function renderConnectionDetails(segments) {
        if (!segments || !segments.length) return '';
        var html = '<div class="relative pl-5 mt-1"><div class="absolute left-[7px] top-2 bottom-2 w-px bg-blue-200"></div>';
        for (var si = 0; si < segments.length; si++) {
            var seg = segments[si];
            var isLast = si === segments.length - 1;
            html += '<div class="relative py-1.5">'
                + '<div class="absolute left-[-15px] top-2.5 w-2 h-2 rounded-full bg-blue-500"></div>'
                + '<div class="flex items-start gap-2"><div class="min-w-0 flex-1">'
                + '<div class="flex items-center gap-1.5 flex-wrap">'
                + '<span class="text-xs font-bold text-gray-800">' + escHtml(seg.DEPARTURE_TIME) + '</span>'
                + '<span class="text-xs text-gray-500">' + escHtml(seg.DEPARTURE_LOCATION) + '</span>'
                + '<span class="text-[11px] text-gray-300">&rarr;</span>'
                + '<span class="text-xs font-bold text-gray-800">' + escHtml(seg.ARRIVAL_TIME) + '</span>'
                + '<span class="text-xs text-gray-500">' + escHtml(seg.ARRIVAL_LOCATION) + '</span>'
                + '</div>'
                + '<div class="flex items-center gap-2 mt-0.5">';
            if (seg.FLIGHT_NUMBER) html += '<span class="text-[11px] text-gray-400">' + escHtml(seg.FLIGHT_NUMBER) + '</span>';
            if (seg.FLIGHT_DURATION) html += '<span class="text-[11px] text-gray-400">&middot; ' + escHtml(seg.FLIGHT_DURATION) + '</span>';
            if (seg.OP && seg.OP !== seg.FLIGHT_NUMBER) html += '<span class="text-[11px] text-gray-400">&middot; ' + escHtml(seg.OP) + '</span>';
            html += '</div></div></div></div>';

            if (!isLast && seg.TIME_WAITING) {
                html += '<div class="relative py-0.5">'
                    + '<div class="absolute left-[-15px] top-1/2 -translate-y-1/2 w-2 h-2 rounded-full border-2 border-blue-300 bg-white"></div>'
                    + '<span class="inline-flex items-center gap-1 text-[11px] bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full font-medium">'
                    + '<svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                    + 'Espera ' + escHtml(seg.TIME_WAITING) + ' em ' + escHtml(seg.ARRIVAL_LOCATION)
                    + '</span></div>';
            }
        }
        html += '</div>';
        return html;
    }

    function renderFlightOption(flight, fi, gIdx, dir, collapseAfter) {
        var conns = flight.connection || [];
        var isDirect = !Array.isArray(conns) || conns.length <= 1;
        var connCount = Array.isArray(conns) ? Math.max(0, conns.length - 1) : 0;
        var cia = displayCia(flight.operator, flight.flight_number);
        var isFirst = fi === 0;
        var isHidden = fi >= collapseAfter;
        var connId = 'conn-' + dir + '-' + gIdx + '-' + fi;

        var wrapClass = isHidden ? 'collapsed-' + dir + '-' + gIdx + ' hidden' : '';
        var borderClass = isFirst ? 'border-blue-400 bg-blue-50/60 shadow-sm' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50';
        var dotBorder = isFirst ? 'border-blue-600' : 'border-gray-300';
        var dotInner = isFirst ? 'bg-blue-600' : '';

        var html = '<div class="' + wrapClass + '">'
            + '<label class="flight-option block p-3 sm:p-4 rounded-xl border cursor-pointer transition-all ' + borderClass + '" data-group="' + gIdx + '" data-dir="' + dir + '">'
            + '<input type="radio" name="group_' + gIdx + '_' + dir + '" value="' + fi + '" class="sr-only ' + dir + '-radio" data-group="' + gIdx + '"' + (isFirst ? ' checked' : '') + '>'
            + '<div class="flex items-start gap-2 sm:gap-3">'
            + '<div class="radio-dot w-5 h-5 rounded-full border-2 shrink-0 flex items-center justify-center mt-1 ' + dotBorder + '">'
            + '<div class="radio-dot-inner w-2.5 h-2.5 rounded-full ' + dotInner + '"></div></div>'
            + '<div class="flex-1 min-w-0">'
            + '<div class="flex items-center gap-2 mb-2 text-xs text-gray-500">'
            + '<span class="font-bold text-gray-700">' + escHtml(cia) + '</span>';
        if (flight.flight_number) html += '<span class="text-gray-400 text-[11px]">voo ' + escHtml(flight.flight_number) + '</span>';
        html += '</div>'
            + '<div class="flex items-center">'
            + '<div class="text-center shrink-0">'
            + '<p class="text-base sm:text-lg font-bold text-gray-800 leading-tight">' + escHtml(flight.departure_time) + '</p>'
            + '<p class="text-[11px] font-medium text-gray-500">' + escHtml(flight.departure_location) + '</p></div>'
            + '<div class="flex-1 mx-2 sm:mx-4 flex flex-col items-center min-w-[70px] sm:min-w-[80px]">';
        if (isDirect) {
            html += '<span class="text-[11px] text-emerald-600 font-bold">Direto</span>';
        } else {
            html += '<button type="button" class="conn-toggle-btn text-[11px] text-amber-600 font-bold hover:text-amber-700 cursor-pointer" data-target="' + connId + '">' + connCount + ' conexão ▾</button>';
        }
        html += '<div class="w-full flex items-center gap-0.5 my-0.5">'
            + '<div class="w-1.5 h-1.5 rounded-full border border-gray-400"></div>'
            + '<div class="flex-1 border-t border-gray-300"></div>';
        if (!isDirect) {
            for (var s = 0; s < connCount; s++) {
                html += '<div class="w-1.5 h-1.5 rounded-full bg-gray-400"></div><div class="flex-1 border-t border-gray-300"></div>';
            }
        }
        html += '<svg class="w-3 h-3 text-gray-400 -ml-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>'
            + '</div>'
            + '<span class="text-[11px] text-gray-400 font-medium">' + escHtml(flight.total_flight_duration) + '</span></div>'
            + '<div class="text-center shrink-0">'
            + '<p class="text-base sm:text-lg font-bold text-gray-800 leading-tight">' + escHtml(flight.arrival_time) + '</p>'
            + '<p class="text-[11px] font-medium text-gray-500">' + escHtml(flight.arrival_location) + '</p></div>';
        if (!isDirect) {
            html += '<button type="button" class="conn-toggle-btn hidden sm:inline-flex text-[11px] text-blue-600 hover:text-blue-700 font-medium whitespace-nowrap ml-3 shrink-0" data-target="' + connId + '">Detalhes</button>';
        }
        html += '</div></div></div></label>';
        if (!isDirect) {
            html += '<div id="' + connId + '" class="conn-details hidden ml-8 mr-2 mt-1 mb-1 bg-gray-50 rounded-lg px-4 py-2 border border-gray-100">'
                + renderConnectionDetails(conns) + '</div>';
        }
        html += '</div>';
        return html;
    }

    function renderCard(group, gIdx) {
        var obFlights = group.outbound_flights;
        var ibFlights = group.inbound_flights || [];
        var totalPrice = group.total_price;
        var sameCia = group.same_cia;
        var airlines = group.airlines || [];
        var hasInbound = ibFlights.length > 0;
        var collapseAfter = 2;

        var hasDirect = false, hasConn = false;
        obFlights.concat(ibFlights).forEach(function(f) {
            var c = f.connection || [];
            if (!Array.isArray(c) || c.length <= 1) hasDirect = true; else hasConn = true;
        });

        var pixOn = CONFIG.pixEnabled && CONFIG.pixDiscount > 0;
        var pixPrice = pixOn ? Math.round(totalPrice * (1 - CONFIG.pixDiscount / 100) * 100) / 100 : totalPrice;
        var pixSavings = pixOn ? Math.round((totalPrice - pixPrice) * 100) / 100 : 0;

        var obTax = parseTaxValue(obFlights[0] ? obFlights[0].boarding_tax : '0');
        var ibTax = hasInbound ? parseTaxValue(ibFlights[0] ? ibFlights[0].boarding_tax : '0') : 0;
        var totalTax = Math.round((obTax + ibTax) * 100) / 100;
        var basePrice = Math.round((totalPrice - totalTax) * 100) / 100;
        var totalPax = CONFIG.totalPax;
        var totalAllPax = Math.round(totalPrice * totalPax * 100) / 100;
        var pixAllPax = pixOn ? Math.round(pixPrice * totalPax * 100) / 100 : totalAllPax;

        var obPeriodsStr = (group.outbound_periods || []).join(',');
        var ibPeriodsStr = (group.inbound_periods || []).join(',');
        var airlinesStr = airlines.map(function(a) { return a.toLowerCase(); }).join(',');

        var obFirst = obFlights[0] || {};
        var ibFirst = hasInbound ? (ibFlights[0] || {}) : {};
        var obJson = JSON.stringify(obFirst);
        var ibJson = hasInbound ? JSON.stringify(ibFirst) : '';

        var html = '<div class="combination-card bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200 animate-fadeInUp"'
            + ' data-airlines="' + escHtml(airlinesStr) + '"'
            + ' data-has-direct="' + (hasDirect ? '1' : '0') + '"'
            + ' data-has-connection="' + (hasConn ? '1' : '0') + '"'
            + ' data-outbound-period="' + escHtml(obPeriodsStr) + '"'
            + ' data-inbound-period="' + escHtml(ibPeriodsStr) + '"'
            + ' data-price="' + totalPrice + '"'
            + ' data-same-cia="' + (sameCia ? '1' : '0') + '"'
            + ' data-group="' + gIdx + '">';

        html += '<div class="flex flex-col lg:flex-row">';

        // --- Left column: flights ---
        html += '<div class="flex-1 min-w-0">';

        // Mobile price header
        html += '<div class="lg:hidden sticky top-16 z-10 flex items-center justify-between px-4 py-3 bg-white border-b border-gray-100 rounded-t-xl shadow-sm">'
            + '<div class="whitespace-nowrap">';
        if (pixOn) {
            html += '<p class="text-[11px] text-gray-400 line-through">R$ ' + formatBRL(totalPrice) + '</p>'
                + '<div class="flex items-center gap-1.5">'
                + '<p class="text-lg font-bold text-emerald-600">R$ ' + formatBRL(pixPrice) + '</p>'
                + '<span class="text-[10px] font-semibold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full">No Pix</span></div>';
        } else {
            html += '<p class="text-lg font-bold text-gray-900">R$ ' + formatBRL(totalPrice) + '</p>';
        }
        html += '<p class="text-[11px] text-gray-400">Por adulto' + (hasInbound ? ', ida e volta' : '') + '</p>';
        if (totalPax > 1) {
            html += '<p class="text-[11px] text-gray-500 font-medium">Total (' + totalPax + 'x): R$ ' + formatBRL(pixOn ? pixAllPax : totalAllPax) + '</p>';
        }
        html += '</div>';

        // Mobile buy button
        html += '<form action="' + escHtml(CONFIG.selectUrl) + '" method="POST" class="group-form" data-group="' + gIdx + '">'
            + '<input type="hidden" name="_token" value="' + escHtml(CONFIG.csrfToken) + '">'
            + '<input type="hidden" name="search_id" value="' + escHtml(CONFIG.searchId) + '">'
            + '<input type="hidden" name="outbound" class="selected-ob" value=\'' + obJson.replace(/'/g, '&#39;') + '\'>'
            + '<input type="hidden" name="ob_provider" class="meta-ob-provider" value="' + escHtml(obFirst._provider || '') + '">'
            + '<input type="hidden" name="ob_pricing_type" class="meta-ob-pricing" value="' + escHtml(obFirst._pricing_type || '') + '">';
        if (hasInbound) {
            html += '<input type="hidden" name="inbound" class="selected-ib" value=\'' + ibJson.replace(/'/g, '&#39;') + '\'>'
                + '<input type="hidden" name="ib_provider" class="meta-ib-provider" value="' + escHtml(ibFirst._provider || '') + '">'
                + '<input type="hidden" name="ib_pricing_type" class="meta-ib-pricing" value="' + escHtml(ibFirst._pricing_type || '') + '">';
        }
        html += '<button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-xl transition-colors text-sm">Comprar</button>'
            + '</form></div>';

        html += '<div class="px-5 pb-5">';

        // Outbound section
        html += '<div class="flex items-center justify-between pt-4 pb-2">'
            + '<div class="flex items-center gap-2">'
            + '<svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>'
            + '<p class="text-sm font-bold text-gray-800">Ida</p></div>';
        if (CONFIG.obDateFormatted) html += '<span class="text-xs text-gray-500 font-medium">' + escHtml(CONFIG.obDateFormatted) + '</span>';
        html += '</div><div class="space-y-2 mb-4">';
        for (var fi = 0; fi < obFlights.length; fi++) {
            html += renderFlightOption(obFlights[fi], fi, gIdx, 'ob', collapseAfter);
        }
        if (obFlights.length > collapseAfter) {
            html += '<button type="button" class="toggle-more-btn w-full text-center text-sm text-blue-600 font-medium py-2 hover:text-blue-700"'
                + ' data-target="collapsed-ob-' + gIdx + '" data-count="' + (obFlights.length - collapseAfter) + '">'
                + '+ ' + (obFlights.length - collapseAfter) + ' opções de ida</button>';
        }
        html += '</div>';

        // Inbound section
        if (hasInbound) {
            html += '<div class="border-t border-gray-100 pt-3">'
                + '<div class="flex items-center justify-between pb-2">'
                + '<div class="flex items-center gap-2">'
                + '<svg class="w-4 h-4 text-blue-600 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>'
                + '<p class="text-sm font-bold text-gray-800">Volta</p></div>';
            if (CONFIG.ibDateFormatted) html += '<span class="text-xs text-gray-500 font-medium">' + escHtml(CONFIG.ibDateFormatted) + '</span>';
            html += '</div><div class="space-y-2 mb-2">';
            for (var ifi = 0; ifi < ibFlights.length; ifi++) {
                html += renderFlightOption(ibFlights[ifi], ifi, gIdx, 'ib', collapseAfter);
            }
            if (ibFlights.length > collapseAfter) {
                html += '<button type="button" class="toggle-more-btn w-full text-center text-sm text-blue-600 font-medium py-2 hover:text-blue-700"'
                    + ' data-target="collapsed-ib-' + gIdx + '" data-count="' + (ibFlights.length - collapseAfter) + '">'
                    + '+ ' + (ibFlights.length - collapseAfter) + ' opções de volta</button>';
            }
            html += '</div></div>';
        }

        html += '</div></div>';

        // --- Right column: price sidebar (desktop) ---
        html += '<div class="hidden lg:flex lg:w-60 shrink-0 border-l border-gray-100">'
            + '<div class="sticky top-20 self-start w-full p-5 flex flex-col text-sm">';

        if (pixOn) {
            html += '<div class="flex items-center gap-1.5 mb-3">'
                + '<svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                + '<span class="text-xs font-bold text-emerald-700">Economia de R$ ' + formatBRL(pixSavings) + '</span></div>'
                + '<p class="text-xs text-gray-400 line-through">R$ ' + formatBRL(totalPrice) + '</p>'
                + '<div class="flex items-baseline gap-1.5">'
                + '<p class="text-2xl font-bold text-gray-900 whitespace-nowrap">R$ ' + formatBRL(pixPrice) + '</p>'
                + '<span class="text-xs font-semibold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full whitespace-nowrap">No Pix</span></div>'
                + '<p class="text-[11px] text-gray-400 mb-3">Por adulto' + (hasInbound ? ', ida e volta' : '') + '</p>'
                + '<div class="space-y-1.5 text-xs text-gray-500 border-t border-gray-100 pt-3 mb-3">'
                + '<div class="flex justify-between"><span>' + totalPax + ' ' + (totalPax > 1 ? 'adultos' : 'adulto') + '</span><span class="text-gray-700 font-medium">R$ ' + formatBRL(basePrice) + '</span></div>'
                + '<div class="flex justify-between"><span>Valor das taxas</span><span class="text-gray-700 font-medium">R$ ' + formatBRL(totalTax) + '</span></div>'
                + '<div class="flex justify-between text-emerald-600"><span>Desconto no Pix</span><span class="font-medium">-R$ ' + formatBRL(pixSavings) + '</span></div>'
                + '<div class="flex justify-between font-bold text-gray-800 pt-1.5 border-t border-gray-100"><span>' + (totalPax > 1 ? 'Total por adulto' : 'Total') + ' no Pix</span><span>R$ ' + formatBRL(pixPrice) + '</span></div>';
            if (totalPax > 1) {
                html += '<div class="flex justify-between font-bold text-blue-700 bg-blue-50 -mx-1 px-1 py-1 rounded"><span>Total (' + totalPax + 'x)</span><span>R$ ' + formatBRL(pixAllPax) + '</span></div>';
            }
            html += '</div>'
                + '<p class="text-[11px] text-gray-400 mb-4">Ou em até <b class="text-gray-600">' + CONFIG.maxInstallments + 'x</b> no cartão de crédito</p>';
        } else {
            html += '<p class="text-2xl font-bold text-gray-900 whitespace-nowrap mb-0.5">R$ ' + formatBRL(totalPrice) + '</p>'
                + '<p class="text-[11px] text-gray-400 mb-3">Por adulto' + (hasInbound ? ', ida e volta' : '') + '</p>'
                + '<div class="space-y-1.5 text-xs text-gray-500 border-t border-gray-100 pt-3 mb-4">'
                + '<div class="flex justify-between"><span>' + totalPax + ' ' + (totalPax > 1 ? 'adultos' : 'adulto') + '</span><span class="text-gray-700 font-medium">R$ ' + formatBRL(basePrice) + '</span></div>'
                + '<div class="flex justify-between"><span>Valor das taxas</span><span class="text-gray-700 font-medium">R$ ' + formatBRL(totalTax) + '</span></div>';
            if (totalPax > 1) {
                html += '<div class="flex justify-between font-bold text-blue-700 bg-blue-50 -mx-1 px-1 py-1 rounded pt-1.5 border-t border-gray-100"><span>Total (' + totalPax + 'x)</span><span>R$ ' + formatBRL(totalAllPax) + '</span></div>';
            }
            html += '</div>';
        }

        // Desktop buy form
        html += '<form action="' + escHtml(CONFIG.selectUrl) + '" method="POST" class="group-form w-full" data-group="' + gIdx + '">'
            + '<input type="hidden" name="_token" value="' + escHtml(CONFIG.csrfToken) + '">'
            + '<input type="hidden" name="search_id" value="' + escHtml(CONFIG.searchId) + '">'
            + '<input type="hidden" name="outbound" class="selected-ob" value=\'' + obJson.replace(/'/g, '&#39;') + '\'>'
            + '<input type="hidden" name="ob_provider" class="meta-ob-provider" value="' + escHtml(obFirst._provider || '') + '">'
            + '<input type="hidden" name="ob_pricing_type" class="meta-ob-pricing" value="' + escHtml(obFirst._pricing_type || '') + '">';
        if (hasInbound) {
            html += '<input type="hidden" name="inbound" class="selected-ib" value=\'' + ibJson.replace(/'/g, '&#39;') + '\'>'
                + '<input type="hidden" name="ib_provider" class="meta-ib-provider" value="' + escHtml(ibFirst._provider || '') + '">'
                + '<input type="hidden" name="ib_pricing_type" class="meta-ib-pricing" value="' + escHtml(ibFirst._pricing_type || '') + '">';
        }
        html += '<button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl transition-colors text-sm">Comprar</button>'
            + '</form></div></div>';

        html += '</div></div>';
        return html;
    }

    // ========================================
    // RENDER ALL GROUPS
    // ========================================
    function renderGroups() {
        var list = document.getElementById('combinations-list');

        var html = '';
        for (var i = 0; i < groupsData.length; i++) {
            html += renderCard(groupsData[i], i);
        }
        list.innerHTML = html;

        if (groupsData.length === 0 && isSearchComplete()) {
            document.getElementById('no-results-msg').classList.remove('hidden');
            document.getElementById('no-results-msg').querySelector('p').textContent = 'Nenhum voo encontrado. Tente alterar as datas ou os aeroportos da sua busca.';
        } else {
            document.getElementById('no-results-msg').classList.add('hidden');
        }

        document.getElementById('results-count').textContent = groupsData.length + ' resultado' + (groupsData.length !== 1 ? 's' : '');
        currentPage = 1;
        applyFilters();
    }

    // ========================================
    // AIRLINE FILTER PILLS (dynamic)
    // ========================================
    function updateAirlineFilters() {
        var airlines = {};
        groupsData.forEach(function(g) {
            (g.airlines || []).forEach(function(a) { airlines[a.toUpperCase()] = true; });
        });
        var list = Object.keys(airlines).sort();

        var checkedCias = getCheckedValues('.filter-cia');

        ['desktop-cia-filter', 'mobile-cia-filter'].forEach(function(containerId) {
            var container = document.getElementById(containerId);
            var pillsId = containerId.replace('-filter', '-pills');
            var pills = document.getElementById(pillsId);
            if (list.length === 0) {
                container.classList.add('hidden');
                return;
            }
            container.classList.remove('hidden');
            var html = '';
            list.forEach(function(cia) {
                var checked = checkedCias.indexOf(cia.toLowerCase()) !== -1;
                var size = containerId.indexOf('mobile') !== -1 ? 'px-4 py-2 text-sm' : 'px-3 py-1.5 text-xs';
                html += '<label class="filter-pill cursor-pointer">'
                    + '<input type="checkbox" class="filter-cia sr-only" value="' + cia.toLowerCase() + '"' + (checked ? ' checked' : '') + '>'
                    + '<span class="filter-pill-label inline-flex items-center ' + size + ' rounded-full font-medium border transition-all">' + escHtml(cia) + '</span>'
                    + '</label>';
            });
            pills.innerHTML = html;
        });

        bindFilterEvents();
    }

    // ========================================
    // FILTERS
    // ========================================
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

            var ciaOk = ciaVals.length === 0 || cardAirlines.some(function(a) { return ciaVals.indexOf(a) !== -1; });
            var stopOk = stopVals.length === 0 || (hasDirect && stopVals.indexOf('direct') !== -1) || (hasConn && stopVals.indexOf('connection') !== -1);
            var obOk = obPeriodVals.length === 0 || obPeriods.some(function(p) { return obPeriodVals.indexOf(p) !== -1; });
            var ibOk = !CONFIG.isRoundtrip || ibPeriodVals.length === 0 || ibPeriods.some(function(p) { return ibPeriodVals.indexOf(p) !== -1; });

            var pass = ciaOk && stopOk && obOk && ibOk;
            card.dataset.filtered = pass ? '1' : '0';
            if (pass) total++;
        });

        currentPage = 1;
        paginate();
        document.getElementById('results-count').textContent = total + ' resultado' + (total !== 1 ? 's' : '');

        var noMsg = document.getElementById('no-results-msg');
        if (total === 0 && groupsData.length > 0) {
            noMsg.classList.remove('hidden');
            noMsg.querySelector('p').textContent = 'Nenhum resultado com os filtros selecionados.';
        } else {
            noMsg.classList.add('hidden');
        }
    }

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

    window.clearFilters = function() {
        document.querySelectorAll('.filter-cia, .filter-stops, .filter-ob-period, .filter-ib-period').forEach(function(el) {
            el.checked = false;
        });
        applyFilters();
        updateFilterCount();
    };

    function updateFilterCount() {
        var checked = 0;
        document.querySelectorAll('.filter-cia:checked, .filter-stops:checked, .filter-ob-period:checked, .filter-ib-period:checked').forEach(function() { checked++; });
        var deduped = Math.floor(checked / 2);
        ['active-filter-count', 'mobile-active-filter-count', 'mobile-btn-filter-count'].forEach(function(id) {
            var badge = document.getElementById(id);
            if (!badge) return;
            if (deduped > 0) { badge.textContent = deduped; badge.classList.remove('hidden'); }
            else { badge.classList.add('hidden'); }
        });
    }

    function bindFilterEvents() {
        document.querySelectorAll('.filter-cia, .filter-stops, .filter-ob-period, .filter-ib-period').forEach(function(el) {
            el.removeEventListener('change', onFilterChange);
            el.addEventListener('change', onFilterChange);
        });
    }

    function onFilterChange() {
        var cls = this.className.match(/filter-[\w-]+/)[0];
        var val = this.value;
        var checked = this.checked;
        document.querySelectorAll('.' + cls + '[value="' + val + '"]').forEach(function(other) {
            other.checked = checked;
        });
        applyFilters();
        updateFilterCount();
    }

    // ========================================
    // SORTING
    // ========================================
    document.querySelectorAll('.sort-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.sort-tab').forEach(function(t) {
                t.classList.remove('text-white', 'bg-blue-600', 'sort-tab-active');
                t.classList.add('text-gray-600');
            });
            tab.classList.remove('text-gray-600');
            tab.classList.add('text-white', 'bg-blue-600', 'sort-tab-active');
            currentSort = tab.dataset.sort;
            knownGroupKeys = {};
            doRender();
        });
    });

    // ========================================
    // EVENT DELEGATION (flight selection, connections, toggles, forms)
    // ========================================
    document.addEventListener('click', function(e) {
        // Connection toggle
        var connBtn = e.target.closest('.conn-toggle-btn');
        if (connBtn) {
            e.preventDefault();
            e.stopPropagation();
            var target = document.getElementById(connBtn.dataset.target);
            if (!target) return;
            var isHidden = target.classList.contains('hidden');
            target.classList.toggle('hidden');
            connBtn.textContent = isHidden
                ? connBtn.textContent.trim().replace('▾', '▴')
                : connBtn.textContent.trim().replace('▴', '▾');
            return;
        }

        // Toggle more flights
        var moreBtn = e.target.closest('.toggle-more-btn');
        if (moreBtn) {
            var tgt = moreBtn.dataset.target;
            var count = moreBtn.dataset.count;
            var items = document.querySelectorAll('.' + tgt);
            if (!items.length) return;
            var wasHidden = items[0].classList.contains('hidden');
            items.forEach(function(el) { el.classList.toggle('hidden'); });
            if (wasHidden) {
                moreBtn.textContent = 'Ver menos';
            } else {
                var dir = tgt.indexOf('-ob-') !== -1 ? 'ida' : 'volta';
                moreBtn.textContent = '+ ' + count + ' opções de ' + dir;
            }
            return;
        }

        // Flight option selection + toggle connections
        var flightOpt = e.target.closest('.flight-option');
        if (flightOpt && !e.target.closest('.conn-toggle-btn')) {
            var gIdx = parseInt(flightOpt.dataset.group);
            var dir = flightOpt.dataset.dir;
            var radio = flightOpt.querySelector('input[type="radio"]');
            if (!radio || e.target.tagName === 'INPUT') return;
            radio.checked = true;

            // Toggle connection details on click anywhere in the flight option
            var connToggle = flightOpt.querySelector('.conn-toggle-btn');
            if (connToggle) {
                var target = document.getElementById(connToggle.dataset.target);
                if (target) {
                    var isHidden = target.classList.contains('hidden');
                    target.classList.toggle('hidden');
                    connToggle.textContent = isHidden
                        ? connToggle.textContent.trim().replace('▾', '▴')
                        : connToggle.textContent.trim().replace('▴', '▾');
                }
            }

            var group = groupsData[gIdx];
            if (!group) return;
            var flights = dir === 'ob' ? group.outbound_flights : group.inbound_flights;
            var flight = flights[parseInt(radio.value)];
            if (!flight) return;

            document.querySelectorAll('.group-form[data-group="' + gIdx + '"]').forEach(function(form) {
                var input = form.querySelector(dir === 'ob' ? '.selected-ob' : '.selected-ib');
                if (input) input.value = JSON.stringify(flight);
                var provInput = form.querySelector(dir === 'ob' ? '.meta-ob-provider' : '.meta-ib-provider');
                if (provInput) provInput.value = flight._provider || '';
                var ptInput = form.querySelector(dir === 'ob' ? '.meta-ob-pricing' : '.meta-ib-pricing');
                if (ptInput) ptInput.value = flight._pricing_type || '';
            });

            flightOpt.closest('.space-y-2').querySelectorAll('.flight-option[data-dir="' + dir + '"]').forEach(function(opt) {
                opt.classList.remove('border-blue-400', 'bg-blue-50/60', 'shadow-sm');
                opt.classList.add('border-gray-200');
                var d = opt.querySelector('.radio-dot');
                if (d) { d.classList.remove('border-blue-600'); d.classList.add('border-gray-300'); }
                var inner = opt.querySelector('.radio-dot-inner');
                if (inner) inner.classList.remove('bg-blue-600');
            });

            flightOpt.classList.remove('border-gray-200');
            flightOpt.classList.add('border-blue-400', 'bg-blue-50/60', 'shadow-sm');
            var dot = flightOpt.querySelector('.radio-dot');
            if (dot) { dot.classList.remove('border-gray-300'); dot.classList.add('border-blue-600'); }
            var inner = flightOpt.querySelector('.radio-dot-inner');
            if (inner) inner.classList.add('bg-blue-600');
        }
    });

    var formSubmitting = false;
    document.addEventListener('submit', function(e) {
        var form = e.target.closest('.group-form');
        if (form) {
            if (formSubmitting) {
                e.preventDefault();
                return;
            }
            formSubmitting = true;
            form.querySelectorAll('button[type="submit"]').forEach(function(btn) {
                btn.disabled = true;
                btn.textContent = 'Aguarde...';
            });
            showTravelLoading({
                title: 'Carregando detalhes do voo...',
                messages: ['Verificando disponibilidade...', 'Consultando preço atualizado...', 'Preparando seu checkout...'],
                timeoutMs: 45000
            });
        }
    });

    // ========================================
    // PROGRESS BAR
    // ========================================
    function isSearchComplete() {
        var slots = CONFIG.providerSlots;
        for (var i = 0; i < slots.length; i++) {
            if (providerStatus[slots[i].key] === 'loading') return false;
        }
        return true;
    }

    function renderProgressBar() {
        var bar = document.getElementById('search-progress');
        var fill = document.getElementById('progress-fill');
        var text = document.getElementById('progress-text');
        var spinner = document.getElementById('progress-spinner');

        var total = CONFIG.providerSlots.length;
        var done = 0;
        CONFIG.providerSlots.forEach(function(s) {
            if (providerStatus[s.key] === 'done' || providerStatus[s.key] === 'error') done++;
        });
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        fill.style.width = pct + '%';

        var complete = isSearchComplete();
        var totalFlights = allOutbound.length + allInbound.length + patriaOutbound.length + patriaInbound.length;

        if (complete) {
            fill.style.width = '100%';
            fill.classList.remove('bg-blue-600');
            fill.classList.add('bg-emerald-500');
            text.textContent = 'Busca completa — ' + totalFlights + ' voo' + (totalFlights !== 1 ? 's' : '') + ' encontrado' + (totalFlights !== 1 ? 's' : '');
            spinner.innerHTML = '<svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            setTimeout(function() { bar.style.opacity = '0'; bar.style.transition = 'opacity 0.5s'; setTimeout(function() { bar.style.display = 'none'; }, 500); }, 3000);
        } else {
            text.textContent = 'Buscando os melhores voos...';
        }
    }

    // ========================================
    // PROGRESSIVE FETCH
    // ========================================
    function startFetching() {
        CONFIG.providerSlots.forEach(function(slot) {
            providerStatus[slot.key] = 'loading';
        });
        renderProgressBar();

        var baseParams = {
            departure: CONFIG.params.departure,
            arrival: CONFIG.params.arrival,
            outbound_date: CONFIG.params.outbound_date,
            adults: CONFIG.params.adults,
            children: CONFIG.params.children,
            infants: CONFIG.params.infants,
            cabin: CONFIG.params.cabin
        };
        if (CONFIG.params.inbound_date) baseParams.inbound_date = CONFIG.params.inbound_date;

        var promises = CONFIG.providerSlots.map(function(slot) {
            var qs = new URLSearchParams(baseParams);
            qs.set('slot', slot.token);

            return fetch('/api/search/provider?' + qs.toString())
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function(data) {
                    providerStatus[slot.key] = 'done';
                    var ob = data.outbound || [];
                    var ib = data.inbound || [];

                    if (slot.p) {
                        patriaOutbound = patriaOutbound.concat(ob);
                        patriaInbound = patriaInbound.concat(ib);
                    } else {
                        allOutbound = allOutbound.concat(ob);
                        allInbound = allInbound.concat(ib);
                    }

                    rebuildAndRender();
                })
                .catch(function(err) {
                    providerStatus[slot.key] = 'error';
                    renderProgressBar();
                });
        });

        Promise.allSettled(promises).then(function() {
            renderProgressBar();
        });
    }

    function groupKey(g) {
        return (g.airlines || []).slice().sort().join('+') + '|' + g.total_price.toFixed(2);
    }

    function saveSelectionState() {
        var state = { radios: {}, conns: {} };
        document.querySelectorAll('.combination-card').forEach(function(card) {
            var gIdx = card.dataset.group;
            ['ob', 'ib'].forEach(function(dir) {
                var checked = card.querySelector('.' + dir + '-radio:checked');
                if (checked) state.radios[gIdx + '_' + dir] = checked.value;
            });
            card.querySelectorAll('.conn-details').forEach(function(cd) {
                if (!cd.classList.contains('hidden')) state.conns[cd.id] = true;
            });
        });
        return state;
    }

    function restoreSelectionState(state) {
        Object.keys(state.radios).forEach(function(key) {
            var parts = key.split('_');
            var gIdx = parts[0], dir = parts[1];
            var val = state.radios[key];
            var radio = document.querySelector('input.' + dir + '-radio[data-group="' + gIdx + '"][value="' + val + '"]');
            if (!radio) return;
            radio.checked = true;

            var group = groupsData[parseInt(gIdx)];
            if (!group) return;
            var flights = dir === 'ob' ? group.outbound_flights : group.inbound_flights;
            var flight = flights[parseInt(val)];

            document.querySelectorAll('.group-form[data-group="' + gIdx + '"]').forEach(function(form) {
                var input = form.querySelector(dir === 'ob' ? '.selected-ob' : '.selected-ib');
                if (input && flight) input.value = JSON.stringify(flight);
                var provInput = form.querySelector(dir === 'ob' ? '.meta-ob-provider' : '.meta-ib-provider');
                if (provInput && flight) provInput.value = flight._provider || '';
                var ptInput = form.querySelector(dir === 'ob' ? '.meta-ob-pricing' : '.meta-ib-pricing');
                if (ptInput && flight) ptInput.value = flight._pricing_type || '';
            });

            var container = radio.closest('.space-y-2');
            if (container) {
                container.querySelectorAll('.flight-option[data-dir="' + dir + '"]').forEach(function(opt) {
                    opt.classList.remove('border-blue-400', 'bg-blue-50/60', 'shadow-sm');
                    opt.classList.add('border-gray-200');
                    var d = opt.querySelector('.radio-dot');
                    if (d) { d.classList.remove('border-blue-600'); d.classList.add('border-gray-300'); }
                    var inner = opt.querySelector('.radio-dot-inner');
                    if (inner) inner.classList.remove('bg-blue-600');
                });
            }
            var flightOpt = radio.closest('.flight-option');
            if (flightOpt) {
                flightOpt.classList.remove('border-gray-200');
                flightOpt.classList.add('border-blue-400', 'bg-blue-50/60', 'shadow-sm');
                var dot = flightOpt.querySelector('.radio-dot');
                if (dot) { dot.classList.remove('border-gray-300'); dot.classList.add('border-blue-600'); }
                var inner = flightOpt.querySelector('.radio-dot-inner');
                if (inner) inner.classList.add('bg-blue-600');
            }
        });
        Object.keys(state.conns).forEach(function(connId) {
            var el = document.getElementById(connId);
            if (el) el.classList.remove('hidden');
            document.querySelectorAll('.conn-toggle-btn[data-target="' + connId + '"]').forEach(function(btn) {
                btn.textContent = btn.textContent.trim().replace('▾', '▴');
            });
        });
    }

    function stableSort(groups) {
        if (currentSort === 'same-cia') {
            groups.sort(function(a, b) {
                var aSame = a.same_cia ? 0 : 1;
                var bSame = b.same_cia ? 0 : 1;
                if (aSame !== bSame) return aSame - bSame;
                return a.total_price - b.total_price;
            });
        } else {
            groups.sort(function(a, b) { return a.total_price - b.total_price; });
        }

        var known = [];
        var fresh = [];
        groups.forEach(function(g) {
            var k = groupKey(g);
            if (knownGroupKeys[k] !== undefined) {
                known.push({ g: g, pos: knownGroupKeys[k] });
            } else {
                fresh.push(g);
            }
        });

        known.sort(function(a, b) { return a.pos - b.pos; });
        var result = known.map(function(o) { return o.g; });

        fresh.forEach(function(g) {
            var price = g.total_price;
            var inserted = false;
            for (var i = 0; i < result.length; i++) {
                if (price < result[i].total_price) {
                    result.splice(i, 0, g);
                    inserted = true;
                    break;
                }
            }
            if (!inserted) result.push(g);
        });

        knownGroupKeys = {};
        result.forEach(function(g, i) { knownGroupKeys[groupKey(g)] = i; });

        return result;
    }

    function doRender() {
        var ob = deduplicateFlights(mergeWithPatria(allOutbound.slice(), patriaOutbound));
        var ib = deduplicateFlights(mergeWithPatria(allInbound.slice(), patriaInbound));

        var selState = saveSelectionState();

        groupsData = buildGroups(ob, CONFIG.isRoundtrip ? ib : [], CONFIG.isRoundtrip);
        groupsData = stableSort(groupsData);

        if (!firstResultsRendered && groupsData.length > 0) {
            firstResultsRendered = true;
        }

        updateAirlineFilters();
        renderProgressBar();
        renderGroups();
        restoreSelectionState(selState);
    }

    function rebuildAndRender() {
        renderProgressBar();
        if (!firstResultsRendered) {
            doRender();
            return;
        }
        if (renderTimer) clearTimeout(renderTimer);
        renderTimer = setTimeout(doRender, 600);
    }

    // ========================================
    // MOBILE FILTERS
    // ========================================
    window.toggleMobileFilters = function() {
        document.getElementById('mobile-filters').classList.toggle('hidden');
        document.body.classList.toggle('overflow-hidden');
    };

    // ========================================
    // LOAD MORE
    // ========================================
    document.getElementById('load-more-btn').addEventListener('click', function() {
        currentPage++;
        paginate();
    });

    // ========================================
    // LOADING ON NAVIGATION
    // ========================================
    window.addEventListener('beforeunload', function() {
        showTravelLoading({
            title: 'Buscando os melhores voos...',
            messages: ['Consultando companhias aéreas...', 'Verificando disponibilidade...', 'Comparando preços...', 'Quase lá...'],
            timeoutMs: 60000
        });
    });

    // ========================================
    // INIT
    // ========================================
    bindFilterEvents();
    startFetching();
})();
</script>
@endpush
