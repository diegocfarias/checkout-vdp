@extends('layouts.public')

@section('title', 'Passagens Aéreas')
@section('container_class', 'w-full')

@section('content')
<div class="-mx-4 sm:-mx-6 -mt-8">

    {{-- Hero --}}
    <section class="relative bg-gradient-to-br from-blue-600 via-teal-500 to-cyan-600 overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute -top-20 -left-20 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-20 -right-20 w-96 h-96 bg-cyan-400/15 rounded-full blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-blue-400/10 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 py-10 sm:py-14 px-4 sm:px-6">
            <div class="max-w-7xl mx-auto text-center mb-8">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white mb-2 leading-tight">
                    Sua próxima viagem começa aqui
                </h1>
                <p class="text-white/80 text-sm sm:text-base max-w-lg mx-auto">
                    Passagens aéreas com preços exclusivos. Compare e economize!
                </p>
            </div>

            @include('search._search_form')

            <div class="max-w-3xl mx-auto mt-6 flex flex-wrap justify-center gap-x-6 gap-y-2">
                <span class="flex items-center gap-1.5 text-white/70 text-xs sm:text-sm">
                    <svg class="w-3.5 h-3.5 text-white/90 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Emissão oficial
                </span>
                <span class="flex items-center gap-1.5 text-white/70 text-xs sm:text-sm">
                    <svg class="w-3.5 h-3.5 text-white/90 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Até 12x no cartão
                </span>
                <span class="flex items-center gap-1.5 text-white/70 text-xs sm:text-sm">
                    <svg class="w-3.5 h-3.5 text-white/90 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Pagamento seguro
                </span>
            </div>
        </div>
    </section>

    {{-- Vitrine de Ofertas --}}
    @if(isset($showcaseRoutes) && $showcaseRoutes->count() > 0)
    <section class="py-10 sm:py-14 px-4 sm:px-6 bg-gradient-to-b from-gray-50 to-white">
        <div class="max-w-[1400px] mx-auto">
            <div class="text-center mb-6 sm:mb-8">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Ofertas especiais para você ✈️</h2>
            </div>

            @php $routeCount = $showcaseRoutes->count(); @endphp
            <div class="hidden sm:grid {{ $routeCount === 1 ? 'sm:grid-cols-1 max-w-md' : ($routeCount === 2 ? 'sm:grid-cols-2 max-w-3xl' : ($routeCount === 3 ? 'sm:grid-cols-3 max-w-5xl' : 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4')) }} gap-4 lg:gap-5">
                @foreach($showcaseRoutes as $route)
                    @include('search._showcase_card', ['route' => $route])
                @endforeach
            </div>

            <div class="sm:hidden -mx-4 px-4">
                <div class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-4 scrollbar-hide" style="-webkit-overflow-scrolling: touch;">
                    @foreach($showcaseRoutes as $route)
                        <div class="snap-start shrink-0 w-[280px]">
                            @include('search._showcase_card', ['route' => $route])
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @endif

    {{-- Como funciona --}}
    <section class="py-10 sm:py-14 px-4 sm:px-6 {{ isset($showcaseRoutes) && $showcaseRoutes->count() > 0 ? 'bg-gray-50' : 'bg-white' }}">
        <div class="max-w-5xl mx-auto">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 text-center mb-8 sm:mb-10">Como funciona</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 sm:gap-10">
                <div class="text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-1">Busque sua viagem</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Escolha origem, destino, datas e passageiros</p>
                </div>

                <div class="text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-1">Compare preços</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Preços exclusivos usando milhas aéreas</p>
                </div>

                <div class="text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-1">Compre e viaje</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">PIX ou cartão parcelado, passagem rápida</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Banner CTA --}}
    @guest('customer')
    <section class="px-4 sm:px-6">
        <div class="max-w-7xl mx-auto">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl py-8 sm:py-10 px-6 sm:px-10 flex flex-col sm:flex-row items-center justify-between gap-5">
                <div class="text-center sm:text-left">
                    <h2 class="text-lg sm:text-xl font-semibold text-white mb-1">Cadastre-se e acompanhe tudo</h2>
                    <p class="text-blue-100 text-sm">Salve passageiros, acompanhe pedidos e compre mais rápido</p>
                </div>
                <a href="{{ route('customer.register') }}"
                   class="shrink-0 inline-flex items-center gap-2 bg-white text-blue-700 font-semibold px-6 py-3 rounded-xl hover:bg-blue-50 transition-colors shadow-md text-sm">
                    Criar conta grátis
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>
    @endguest

    {{-- Vantagens --}}
    <section class="py-10 sm:py-14 px-4 sm:px-6 bg-white">
        <div class="max-w-7xl mx-auto">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 text-center mb-8">Por que a Voe de Primeira?</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 lg:gap-6">
                <div class="flex items-start gap-4 p-5 rounded-xl bg-gray-50 hover:bg-blue-50/50 transition-colors">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 text-sm mb-0.5">Compra segura</h3>
                        <p class="text-sm text-gray-500 leading-relaxed">Pagamento criptografado e emissão oficial pelas cias aéreas</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 p-5 rounded-xl bg-gray-50 hover:bg-blue-50/50 transition-colors">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 text-sm mb-0.5">Emissão rápida</h3>
                        <p class="text-sm text-gray-500 leading-relaxed">Passagem emitida logo após a confirmação do pagamento</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 p-5 rounded-xl bg-gray-50 hover:bg-blue-50/50 transition-colors">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 text-sm mb-0.5">Parcele em até 12x</h3>
                        <p class="text-sm text-gray-500 leading-relaxed">PIX com desconto ou cartão parcelado sem burocracia</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="py-10 sm:py-14 px-4 sm:px-6 bg-gray-50">
        <div class="max-w-3xl mx-auto">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 text-center mb-6 sm:mb-8">Dúvidas frequentes</h2>

            <div class="space-y-2.5">
                @foreach([
                    ['Como funciona a compra com milhas?', 'Compramos passagens usando milhas aéreas e repassamos preços exclusivos pra você. Você paga em reais via PIX ou cartão e recebe uma passagem emitida direto pela cia aérea.'],
                    ['A passagem é oficial?', 'Sim! Todas as passagens são emitidas oficialmente pelas companhias aéreas. Você recebe o localizador da reserva pra fazer check-in normalmente.'],
                    ['Quanto tempo leva a emissão?', 'Após a confirmação do pagamento, a emissão é realizada rapidamente. Você recebe um e-mail com os dados da reserva.'],
                    ['Posso parcelar?', 'Pode sim! Aceitamos PIX à vista e cartão de crédito em até 12x.'],
                    ['Como acompanho meu pedido?', 'Pelo link que você recebe por e-mail, pela página "Meu pedido" ou pela sua área de cliente.'],
                ] as $faq)
                <details class="bg-white rounded-xl border border-gray-200 group">
                    <summary class="cursor-pointer select-none px-5 py-4 text-sm font-medium text-gray-800 hover:bg-gray-50 transition-colors flex items-center justify-between rounded-xl">
                        {{ $faq[0] }}
                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 group-open:rotate-180 shrink-0 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-5 pb-4">
                        <p class="text-sm text-gray-500 leading-relaxed">{{ $faq[1] }}</p>
                    </div>
                </details>
                @endforeach
            </div>
        </div>
    </section>

</div>
@endsection

@push('styles')
<style>
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
@endpush
