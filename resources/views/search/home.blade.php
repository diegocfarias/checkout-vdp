@extends('layouts.public')

@section('title', 'Passagens Aéreas')

@section('content')
<div class="-mx-4 -mt-8">
    <section class="bg-gradient-to-br from-gray-900 via-gray-800 to-emerald-900 py-12 px-4 sm:py-16">
        <div class="max-w-4xl mx-auto text-center mb-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">Encontre as melhores passagens</h1>
            <p class="text-gray-300 text-base sm:text-lg">Preços exclusivos com milhas aéreas</p>
        </div>

        @include('search._search_form')
    </section>

    <section class="max-w-4xl mx-auto py-12 px-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
                <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="font-semibold text-gray-800 mb-1">Melhores preços</h3>
                <p class="text-sm text-gray-500">Até 50% de desconto usando milhas aéreas</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
                <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3 class="font-semibold text-gray-800 mb-1">Emissão imediata</h3>
                <p class="text-sm text-gray-500">Sua passagem é emitida rapidamente após o pagamento</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
                <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <h3 class="font-semibold text-gray-800 mb-1">Parcele em até 12x</h3>
                <p class="text-sm text-gray-500">Pague com PIX ou cartão de crédito parcelado</p>
            </div>
        </div>
    </section>
</div>
@endsection
