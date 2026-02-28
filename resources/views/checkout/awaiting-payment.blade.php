@extends('layouts.public')

@section('title', 'Aguardando Pagamento')

@section('content')
    <div class="max-w-lg mx-auto text-center">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-8">
            <div class="mx-auto w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-2">Aguardando pagamento</h2>
            <p class="text-gray-500 mb-6">Seu pagamento ainda não foi confirmado. Se você já realizou o pagamento, aguarde alguns instantes e atualize esta página.</p>

            <a href="{{ route('checkout.payment-callback', $order->token) }}"
               class="inline-block bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                Verificar pagamento
            </a>

            @if($order->expires_at)
                <p class="text-xs text-gray-400 mt-4">
                    Link expira em {{ $order->expires_at->format('d/m/Y H:i') }}
                </p>
            @endif
        </div>
    </div>
@endsection
