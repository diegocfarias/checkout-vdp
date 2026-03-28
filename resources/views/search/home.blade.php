@extends('layouts.public')

@section('title', 'Passagens Aéreas')

@section('content')
<div class="-mx-4 -mt-8">
    <section class="bg-gradient-to-br from-gray-900 via-gray-800 to-emerald-900 py-12 px-4 sm:py-16">
        <div class="max-w-5xl mx-auto text-center mb-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">Encontre as melhores passagens</h1>
            <p class="text-gray-300 text-base sm:text-lg">Preços exclusivos com milhas aéreas</p>
        </div>

        @include('search._search_form')
    </section>

    {{-- Como funciona --}}
    <section class="max-w-5xl mx-auto py-14 px-4">
        <h2 class="text-2xl font-bold text-gray-800 text-center mb-10">Como funciona</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <div class="text-xs font-bold text-emerald-600 mb-2">1</div>
                <h3 class="font-semibold text-gray-800 mb-1">Busque sua viagem</h3>
                <p class="text-sm text-gray-500 leading-relaxed">Escolha origem, destino, datas e número de passageiros</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="text-xs font-bold text-emerald-600 mb-2">2</div>
                <h3 class="font-semibold text-gray-800 mb-1">Compare preços</h3>
                <p class="text-sm text-gray-500 leading-relaxed">Veja as melhores opções com preços exclusivos usando milhas aéreas</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div class="text-xs font-bold text-emerald-600 mb-2">3</div>
                <h3 class="font-semibold text-gray-800 mb-1">Compre e viaje</h3>
                <p class="text-sm text-gray-500 leading-relaxed">Pague via PIX ou cartão parcelado e receba sua passagem rapidamente</p>
            </div>
        </div>
    </section>

    {{-- Vantagens --}}
    <section class="bg-gray-100 py-14 px-4">
        <div class="max-w-5xl mx-auto">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-1">Compra segura</h3>
                    <p class="text-sm text-gray-500">Pagamento criptografado e dados protegidos</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-1">Emissão rápida</h3>
                    <p class="text-sm text-gray-500">Sua passagem é emitida rapidamente após a confirmação</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-1">Parcele em até 12x</h3>
                    <p class="text-sm text-gray-500">PIX à vista ou cartão de crédito parcelado</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="max-w-3xl mx-auto py-14 px-4">
        <h2 class="text-2xl font-bold text-gray-800 text-center mb-8">Perguntas frequentes</h2>
        <div class="space-y-3">
            @foreach([
                ['Como funciona a compra com milhas?', 'Compramos passagens usando milhas aéreas e repassamos preços exclusivos para você. Você paga em reais via PIX ou cartão e recebe uma passagem emitida diretamente pela companhia aérea.'],
                ['A passagem é oficial?', 'Sim! Todas as passagens são emitidas oficialmente pelas companhias aéreas. Você recebe o localizador da reserva para fazer check-in normalmente.'],
                ['Quanto tempo leva a emissão?', 'Após a confirmação do pagamento, a emissão é realizada rapidamente. Você será notificado por e-mail com os dados da reserva.'],
                ['Posso parcelar?', 'Sim! Aceitamos pagamento via PIX (à vista) e cartão de crédito em até 12x.'],
                ['Como acompanho meu pedido?', 'Você pode acompanhar o status do seu pedido a qualquer momento pela página "Meu pedido" ou pela sua área de cliente.'],
            ] as $faq)
            <details class="bg-white rounded-xl border border-gray-200 shadow-sm group">
                <summary class="cursor-pointer select-none px-5 py-4 text-sm font-medium text-gray-800 hover:bg-gray-50 transition-colors flex items-center justify-between rounded-xl">
                    {{ $faq[0] }}
                    <svg class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-4">
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $faq[1] }}</p>
                </div>
            </details>
            @endforeach
        </div>
    </section>
</div>
@endsection
