@extends('layouts.public')

@section('title', 'Resultados - Voos')

@section('container_class', 'max-w-6xl')

@section('content')
<div class="space-y-6 pb-8">
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

    @if(count($groups) === 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h2 class="text-xl font-semibold text-gray-700 mb-2">Nenhum voo encontrado</h2>
            <p class="text-gray-500 mb-6">Tente alterar as datas ou os aeroportos da sua busca.</p>
            <a href="{{ route('search.home') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors">Nova busca</a>
        </div>
    @else
        <div class="flex flex-col lg:flex-row gap-5">
            {{-- Sidebar Filtros (desktop) --}}
            <aside class="hidden lg:block w-64 shrink-0">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 sticky top-[72px] overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            <h3 class="font-semibold text-gray-800 text-sm">Filtros</h3>
                            <span id="active-filter-count" class="hidden text-xs font-bold text-white bg-blue-500 rounded-full w-4.5 h-4.5 flex items-center justify-center leading-none px-1.5 py-0.5">0</span>
                        </div>
                        <button type="button" onclick="clearFilters()" class="text-xs text-blue-600 hover:text-blue-700 font-medium transition-colors">Limpar</button>
                    </div>

                    @if(count($airlines) > 0)
                    <div class="px-5 py-4 border-b border-gray-100">
                        <div class="flex items-center gap-1.5 mb-3">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Companhia</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($airlines as $cia)
                            <label class="filter-pill cursor-pointer">
                                <input type="checkbox" class="filter-cia sr-only" value="{{ strtolower($cia) }}" checked>
                                <span class="filter-pill-label inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium border transition-all">{{ strtoupper($cia) }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="px-5 py-4 border-b border-gray-100">
                        <div class="flex items-center gap-1.5 mb-3">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Paradas</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <label class="filter-pill cursor-pointer">
                                <input type="checkbox" class="filter-stops sr-only" value="direct" checked>
                                <span class="filter-pill-label inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border transition-all">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                    Direto
                                </span>
                            </label>
                            <label class="filter-pill cursor-pointer">
                                <input type="checkbox" class="filter-stops sr-only" value="connection" checked>
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
                            @foreach(['madrugada' => ['00h-06h', 'M12 3v1m0 16v1m8.66-13.66l-.71.71M4.05 19.95l-.71.71M21 12h-1M4 12H3m16.95 7.95l-.71-.71M4.05 4.05l-.71-.71'], 'manha' => ['06h-12h', 'M12 3v1m0 0a8 8 0 100 16m0-16a8 8 0 110 16m0 0v1'], 'tarde' => ['12h-18h', 'M12 3v1m4.22 1.78l.71-.71M20 12h1M17.22 17.22l.71.71M12 20v1m-4.22-1.78l-.71.71M4 12H3m1.78-5.22l-.71-.71'], 'noite' => ['18h-00h', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z']] as $key => $info)
                            <label class="filter-pill cursor-pointer">
                                <input type="checkbox" class="filter-ob-period sr-only" value="{{ $key }}" checked>
                                <span class="filter-pill-label inline-flex flex-col items-center gap-0.5 px-2 py-2 rounded-xl text-xs font-medium border transition-all w-full text-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $info[1] }}"/></svg>
                                    {{ $info[0] }}
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
                            @foreach(['madrugada' => ['00h-06h', 'M12 3v1m0 16v1m8.66-13.66l-.71.71M4.05 19.95l-.71.71M21 12h-1M4 12H3m16.95 7.95l-.71-.71M4.05 4.05l-.71-.71'], 'manha' => ['06h-12h', 'M12 3v1m0 0a8 8 0 100 16m0-16a8 8 0 110 16m0 0v1'], 'tarde' => ['12h-18h', 'M12 3v1m4.22 1.78l.71-.71M20 12h1M17.22 17.22l.71.71M12 20v1m-4.22-1.78l-.71.71M4 12H3m1.78-5.22l-.71-.71'], 'noite' => ['18h-00h', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z']] as $key => $info)
                            <label class="filter-pill cursor-pointer">
                                <input type="checkbox" class="filter-ib-period sr-only" value="{{ $key }}" checked>
                                <span class="filter-pill-label inline-flex flex-col items-center gap-0.5 px-2 py-2 rounded-xl text-xs font-medium border transition-all w-full text-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $info[1] }}"/></svg>
                                    {{ $info[0] }}
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
                <div class="flex flex-wrap items-center gap-3 mb-5 sticky top-[72px] lg:static bg-gray-50 lg:bg-transparent -mx-4 px-4 py-3 lg:py-0 lg:mx-0 lg:px-0 z-10 border-b lg:border-0 border-gray-200">
                    <div class="flex bg-white rounded-lg border border-gray-200 overflow-hidden text-sm shadow-sm">
                        <button type="button" data-sort="price" class="sort-tab sort-tab-active px-5 py-2.5 font-medium text-white bg-blue-600">Menor preço</button>
                        @if($mixEnabled)
                        <button type="button" data-sort="same-cia" class="sort-tab px-5 py-2.5 font-medium text-gray-600 hover:bg-gray-50">Mesma cia</button>
                        @endif
                    </div>
                    <span class="text-sm text-gray-500 ml-auto font-medium" id="results-count">{{ count($groups) }} resultado{{ count($groups) !== 1 ? 's' : '' }}</span>

                    <button type="button" onclick="toggleMobileFilters()" class="lg:hidden flex items-center gap-1.5 bg-white border border-gray-200 rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 hover:border-gray-300 shadow-sm transition-colors relative">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filtros
                        <span id="mobile-btn-filter-count" class="hidden absolute -top-1.5 -right-1.5 text-xs font-bold text-white bg-blue-500 rounded-full w-4 h-4 flex items-center justify-center leading-none">0</span>
                    </button>
                </div>

                <div class="space-y-5" id="combinations-list">
                    @foreach($groups as $groupIdx => $group)
                        @include('search._combination_card', ['group' => $group, 'groupIdx' => $groupIdx, 'searchId' => $search->id])
                    @endforeach
                </div>

                <div id="no-results-msg" class="hidden bg-white rounded-xl border border-gray-200 p-8 text-center">
                    <p class="text-gray-500">Nenhum resultado com os filtros selecionados.</p>
                    <button type="button" onclick="clearFilters()" class="mt-3 text-blue-600 font-medium text-sm">Limpar filtros</button>
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
                    @if(count($airlines) > 0)
                    <div>
                        <div class="flex items-center gap-1.5 mb-3">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Companhia</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($airlines as $cia)
                            <label class="filter-pill cursor-pointer">
                                <input type="checkbox" class="filter-cia sr-only" value="{{ strtolower($cia) }}" checked>
                                <span class="filter-pill-label inline-flex items-center px-4 py-2 rounded-full text-sm font-medium border transition-all">{{ strtoupper($cia) }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div>
                        <div class="flex items-center gap-1.5 mb-3">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Paradas</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <label class="filter-pill cursor-pointer">
                                <input type="checkbox" class="filter-stops sr-only" value="direct" checked>
                                <span class="filter-pill-label inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium border transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                    Direto
                                </span>
                            </label>
                            <label class="filter-pill cursor-pointer">
                                <input type="checkbox" class="filter-stops sr-only" value="connection" checked>
                                <span class="filter-pill-label inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium border transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h7m4 0h7"/></svg>
                                    Conexão
                                </span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5 mb-3">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Horário ida</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['madrugada' => ['00h-06h', 'M12 3v1m0 16v1m8.66-13.66l-.71.71M4.05 19.95l-.71.71M21 12h-1M4 12H3m16.95 7.95l-.71-.71M4.05 4.05l-.71-.71'], 'manha' => ['06h-12h', 'M12 3v1m0 0a8 8 0 100 16m0-16a8 8 0 110 16m0 0v1'], 'tarde' => ['12h-18h', 'M12 3v1m4.22 1.78l.71-.71M20 12h1M17.22 17.22l.71.71M12 20v1m-4.22-1.78l-.71.71M4 12H3m1.78-5.22l-.71-.71'], 'noite' => ['18h-00h', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z']] as $key => $info)
                            <label class="filter-pill cursor-pointer">
                                <input type="checkbox" class="filter-ob-period sr-only" value="{{ $key }}" checked>
                                <span class="filter-pill-label inline-flex flex-col items-center gap-1 px-3 py-2.5 rounded-xl text-xs font-medium border transition-all w-full text-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $info[1] }}"/></svg>
                                    {{ $info[0] }}
                                </span>
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
                            @foreach(['madrugada' => ['00h-06h', 'M12 3v1m0 16v1m8.66-13.66l-.71.71M4.05 19.95l-.71.71M21 12h-1M4 12H3m16.95 7.95l-.71-.71M4.05 4.05l-.71-.71'], 'manha' => ['06h-12h', 'M12 3v1m0 0a8 8 0 100 16m0-16a8 8 0 110 16m0 0v1'], 'tarde' => ['12h-18h', 'M12 3v1m4.22 1.78l.71-.71M20 12h1M17.22 17.22l.71.71M12 20v1m-4.22-1.78l-.71.71M4 12H3m1.78-5.22l-.71-.71'], 'noite' => ['18h-00h', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z']] as $key => $info)
                            <label class="filter-pill cursor-pointer">
                                <input type="checkbox" class="filter-ib-period sr-only" value="{{ $key }}" checked>
                                <span class="filter-pill-label inline-flex flex-col items-center gap-1 px-3 py-2.5 rounded-xl text-xs font-medium border transition-all w-full text-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $info[1] }}"/></svg>
                                    {{ $info[0] }}
                                </span>
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
    @endif
</div>
@endsection

@push('styles')
<style>
    .filter-pill input:checked + .filter-pill-label {
        background-color: #ecfdf5;
        border-color: #6ee7b7;
        color: #047857;
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
        color: #059669;
    }
    .filter-pill input:not(:checked) + .filter-pill-label svg {
        color: #9ca3af;
    }
    .sort-tab-active {
        background-color: #059669 !important;
        color: #fff !important;
    }
    .sort-tab-active:hover {
        background-color: #059669 !important;
    }
</style>
@endpush

@push('scripts')
<script>
(function() {
    // --- Toggle inline search form ---
    var toggleBtn = document.getElementById('toggle-search-form');
    var inlineForm = document.getElementById('inline-search-form');
    if (toggleBtn && inlineForm) {
        toggleBtn.addEventListener('click', function() {
            var isHidden = inlineForm.classList.contains('hidden');
            inlineForm.classList.toggle('hidden');
            toggleBtn.textContent = isHidden ? 'Fechar busca' : 'Nova busca';
            if (isHidden) {
                inlineForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }

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

    function toggleConnDetails(connBtn) {
        var connDiv = document.getElementById(connBtn.dataset.target);
        if (!connDiv) return;
        var wasHidden = connDiv.classList.contains('hidden');
        connDiv.classList.toggle('hidden');
        var txt = connBtn.textContent.trim();
        connBtn.textContent = wasHidden ? txt.replace('▾', '▴') : txt.replace('▴', '▾');
    }

    document.querySelectorAll('.flight-option').forEach(function(label) {
        label.addEventListener('click', function(e) {
            if (e.target.closest('.conn-toggle-btn')) return;
            if (e.target.tagName === 'INPUT') return;

            var groupIdx = parseInt(label.dataset.group);
            var dir = label.dataset.dir;
            var radio = label.querySelector('input[type="radio"]');
            if (!radio) return;
            updateFlightSelection(groupIdx, dir, parseInt(radio.value));

            var activeClasses = dir === 'ob'
                ? ['border-blue-400', 'bg-blue-50/60', 'shadow-sm']
                : ['border-blue-400', 'bg-blue-50/60', 'shadow-sm'];
            var dotBorder = dir === 'ob' ? 'border-blue-600' : 'border-blue-600';
            var dotBg = dir === 'ob' ? 'bg-blue-600' : 'bg-blue-600';

            label.closest('.space-y-2').querySelectorAll('.flight-option[data-dir="' + dir + '"]').forEach(function(opt) {
                opt.classList.remove('border-blue-400', 'bg-blue-50/60', 'border-blue-400', 'bg-blue-50/60', 'shadow-sm');
                opt.classList.add('border-gray-200');
                var d = opt.querySelector('.radio-dot');
                if (d) { d.classList.remove('border-blue-600', 'border-blue-600'); d.classList.add('border-gray-300'); }
                var inner = opt.querySelector('.radio-dot-inner');
                if (inner) { inner.classList.remove('bg-blue-600', 'bg-blue-600'); }

                if (opt !== label) {
                    var otherConnBtn = opt.querySelector('.conn-toggle-btn');
                    if (otherConnBtn) {
                        var otherConn = document.getElementById(otherConnBtn.dataset.target);
                        if (otherConn && !otherConn.classList.contains('hidden')) {
                            otherConn.classList.add('hidden');
                            otherConnBtn.textContent = otherConnBtn.textContent.trim().replace('▴', '▾');
                        }
                    }
                }
            });

            label.classList.remove('border-gray-200');
            activeClasses.forEach(function(c) { label.classList.add(c); });
            var d = label.querySelector('.radio-dot');
            if (d) { d.classList.remove('border-gray-300'); d.classList.add(dotBorder); }
            var inner = label.querySelector('.radio-dot-inner');
            if (inner) { inner.classList.add(dotBg); }

            var connBtn = label.querySelector('.conn-toggle-btn');
            if (connBtn) {
                toggleConnDetails(connBtn);
            }
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
        el.addEventListener('change', function() {
            var cls = this.className.match(/filter-[\w-]+/)[0];
            var val = this.value;
            var checked = this.checked;
            document.querySelectorAll('.' + cls + '[value="' + val + '"]').forEach(function(other) {
                other.checked = checked;
            });
            applyFilters();
            updateFilterCount();
        });
    });

    function updateFilterCount() {
        var all = document.querySelectorAll('.filter-cia, .filter-stops, .filter-ob-period, .filter-ib-period');
        var unchecked = 0;
        all.forEach(function(el) { if (!el.checked) unchecked++; });
        var deduped = Math.floor(unchecked / 2);
        var badges = [
            document.getElementById('active-filter-count'),
            document.getElementById('mobile-active-filter-count'),
            document.getElementById('mobile-btn-filter-count')
        ];
        badges.forEach(function(badge) {
            if (!badge) return;
            if (deduped > 0) {
                badge.textContent = deduped;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });
    }

    window.clearFilters = function() {
        document.querySelectorAll('.filter-cia, .filter-stops, .filter-ob-period, .filter-ib-period').forEach(function(el) {
            el.checked = true;
        });
        applyFilters();
        updateFilterCount();
    };

    // --- Sorting ---
    document.querySelectorAll('.sort-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.sort-tab').forEach(function(t) {
                t.classList.remove('text-white', 'bg-blue-600', 'sort-tab-active');
                t.classList.add('text-gray-600');
            });
            tab.classList.remove('text-gray-600');
            tab.classList.add('text-white', 'bg-blue-600', 'sort-tab-active');

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
