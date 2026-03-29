@extends('layouts.public')

@section('title', 'Passagens Aéreas')

@section('content')
<div class="-mx-4 sm:-mx-6 -mt-8">

    {{-- Hero --}}
    <section class="relative bg-gradient-to-br from-gray-900 via-gray-800 to-emerald-900 overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-10 left-10 w-72 h-72 bg-emerald-500 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-emerald-400 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 py-14 sm:py-20 px-4">
            <div class="max-w-6xl mx-auto text-center mb-10">
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white mb-3 leading-tight">
                    Para onde você quer ir?
                </h1>
                <p class="text-gray-300 text-base sm:text-lg max-w-2xl mx-auto">
                    Encontre passagens aéreas com preços exclusivos usando milhas
                </p>
            </div>

            @include('search._search_form')

            <div class="max-w-5xl mx-auto mt-8 flex flex-wrap justify-center gap-x-8 gap-y-3">
                <div class="flex items-center gap-2 text-gray-400 text-sm">
                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Emissão oficial pelas cias aéreas
                </div>
                <div class="flex items-center gap-2 text-gray-400 text-sm">
                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Parcele em até 12x
                </div>
                <div class="flex items-center gap-2 text-gray-400 text-sm">
                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Pagamento seguro
                </div>
            </div>
        </div>
    </section>

    {{-- Vitrine de Ofertas --}}
    @if(isset($showcaseRoutes) && $showcaseRoutes->count() > 0)
    <section class="py-14 sm:py-20 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-10">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Passagens em destaque</h2>
                <p class="text-gray-500 text-sm sm:text-base">Preços atualizados automaticamente. Aproveite as melhores ofertas.</p>
            </div>

            {{-- Desktop: Grid / Mobile: Scroll horizontal --}}
            <div class="hidden sm:grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($showcaseRoutes as $route)
                    @include('search._showcase_card', ['route' => $route])
                @endforeach
            </div>

            <div class="sm:hidden -mx-4 px-4">
                <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory pb-4 scrollbar-hide" style="-webkit-overflow-scrolling: touch;">
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
    <section class="py-14 sm:py-20 px-4 {{ isset($showcaseRoutes) && $showcaseRoutes->count() > 0 ? 'bg-gray-50' : 'bg-white' }}">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Como funciona</h2>
                <p class="text-gray-500 text-sm sm:text-base">Comprar passagens com a gente é simples e rápido</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 sm:gap-6 lg:gap-12 relative">
                {{-- Connecting line (desktop) --}}
                <div class="hidden sm:block absolute top-12 left-[20%] right-[20%] h-0.5 bg-gray-200"></div>

                <div class="relative text-center">
                    <div class="w-20 h-20 bg-white rounded-2xl shadow-md border border-gray-100 flex items-center justify-center mx-auto mb-5 relative z-10">
                        <svg class="w-9 h-9 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <div class="inline-flex items-center justify-center w-7 h-7 bg-emerald-600 text-white text-xs font-bold rounded-full mb-3">1</div>
                    <h3 class="font-bold text-gray-900 text-lg mb-2">Busque sua viagem</h3>
                    <p class="text-sm text-gray-500 leading-relaxed max-w-xs mx-auto">Escolha origem, destino, datas e número de passageiros</p>
                </div>

                <div class="relative text-center">
                    <div class="w-20 h-20 bg-white rounded-2xl shadow-md border border-gray-100 flex items-center justify-center mx-auto mb-5 relative z-10">
                        <svg class="w-9 h-9 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="inline-flex items-center justify-center w-7 h-7 bg-emerald-600 text-white text-xs font-bold rounded-full mb-3">2</div>
                    <h3 class="font-bold text-gray-900 text-lg mb-2">Compare preços</h3>
                    <p class="text-sm text-gray-500 leading-relaxed max-w-xs mx-auto">Veja as melhores opções com preços exclusivos usando milhas aéreas</p>
                </div>

                <div class="relative text-center">
                    <div class="w-20 h-20 bg-white rounded-2xl shadow-md border border-gray-100 flex items-center justify-center mx-auto mb-5 relative z-10">
                        <svg class="w-9 h-9 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="inline-flex items-center justify-center w-7 h-7 bg-emerald-600 text-white text-xs font-bold rounded-full mb-3">3</div>
                    <h3 class="font-bold text-gray-900 text-lg mb-2">Compre e viaje</h3>
                    <p class="text-sm text-gray-500 leading-relaxed max-w-xs mx-auto">Pague via PIX ou cartão parcelado e receba sua passagem rapidamente</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Banner CTA --}}
    @guest('customer')
    <section class="relative overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 py-12 sm:py-16 px-4">
            <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
                <div class="text-center sm:text-left">
                    <h2 class="text-xl sm:text-2xl font-bold text-white mb-2">Crie sua conta gratuita</h2>
                    <p class="text-emerald-100 text-sm sm:text-base">Acompanhe seus pedidos, salve passageiros e agilize suas compras</p>
                </div>
                <a href="{{ route('customer.register') }}"
                   class="shrink-0 inline-flex items-center gap-2 bg-white text-emerald-700 font-semibold px-8 py-3.5 rounded-xl hover:bg-emerald-50 transition-colors shadow-lg">
                    Criar conta
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>
    @endguest

    {{-- Vantagens --}}
    <section class="py-14 sm:py-20 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Por que comprar com a gente?</h2>
                <p class="text-gray-500 text-sm sm:text-base">Milhares de viajantes já escolheram a Voe de Primeira</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 lg:gap-8">
                <div class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-emerald-100 p-7 text-center transition-all duration-300">
                    <div class="w-14 h-14 bg-emerald-50 group-hover:bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-5 transition-colors">
                        <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="font-bold text-gray-900 text-lg mb-2">Compra segura</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Pagamento criptografado, dados protegidos e emissão oficial pelas companhias aéreas</p>
                </div>

                <div class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-emerald-100 p-7 text-center transition-all duration-300">
                    <div class="w-14 h-14 bg-emerald-50 group-hover:bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-5 transition-colors">
                        <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="font-bold text-gray-900 text-lg mb-2">Emissão rápida</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Sua passagem é emitida rapidamente após a confirmação do pagamento</p>
                </div>

                <div class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-emerald-100 p-7 text-center transition-all duration-300">
                    <div class="w-14 h-14 bg-emerald-50 group-hover:bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-5 transition-colors">
                        <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <h3 class="font-bold text-gray-900 text-lg mb-2">Parcele em até 12x</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">PIX à vista com desconto ou cartão de crédito parcelado sem burocracia</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="py-14 sm:py-20 px-4 bg-gray-50">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-10">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Perguntas frequentes</h2>
                <p class="text-gray-500 text-sm sm:text-base">Tire suas dúvidas sobre como funciona</p>
            </div>

            <div class="space-y-3">
                @foreach([
                    ['Como funciona a compra com milhas?', 'Compramos passagens usando milhas aéreas e repassamos preços exclusivos para você. Você paga em reais via PIX ou cartão e recebe uma passagem emitida diretamente pela companhia aérea.'],
                    ['A passagem é oficial?', 'Sim! Todas as passagens são emitidas oficialmente pelas companhias aéreas. Você recebe o localizador da reserva para fazer check-in normalmente.'],
                    ['Quanto tempo leva a emissão?', 'Após a confirmação do pagamento, a emissão é realizada rapidamente. Você será notificado por e-mail com os dados da reserva.'],
                    ['Posso parcelar?', 'Sim! Aceitamos pagamento via PIX (à vista) e cartão de crédito em até 12x.'],
                    ['Como acompanho meu pedido?', 'Você pode acompanhar o status do seu pedido a qualquer momento pela página "Meu pedido" ou pela sua área de cliente.'],
                ] as $faq)
                <details class="bg-white rounded-xl border border-gray-200 shadow-sm group">
                    <summary class="cursor-pointer select-none px-6 py-5 text-sm sm:text-base font-medium text-gray-800 hover:bg-gray-50 transition-colors flex items-center justify-between rounded-xl">
                        {{ $faq[0] }}
                        <svg class="w-5 h-5 text-gray-400 transition-transform duration-200 group-open:rotate-180 shrink-0 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-6 pb-5">
                        <p class="text-sm sm:text-base text-gray-600 leading-relaxed">{{ $faq[1] }}</p>
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
