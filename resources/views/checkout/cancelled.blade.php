@extends('layouts.public')

@section('title', 'Pedido Cancelado')

@section('content')
    <div class="max-w-lg mx-auto text-center">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-8">
            <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-2">Pedido cancelado</h2>
            <p class="text-gray-500 mb-4">O link de pagamento expirou ou o pedido foi cancelado.</p>
            @php $waNum = \App\Models\Setting::get('whatsapp_number'); @endphp
            <p class="text-gray-500">Se ainda deseja prosseguir com a compra, solicite um novo link pelo
                @if($waNum)
                    <a href="https://wa.me/{{ $waNum }}" target="_blank" class="text-green-600 underline font-semibold hover:text-green-800">WhatsApp</a>.
                @else
                    WhatsApp.
                @endif
            </p>
        </div>
    </div>
@endsection
