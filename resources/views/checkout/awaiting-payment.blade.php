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

            @if(isset($payment) && $payment->gateway === 'appmax' && $payment->gateway_response)
                @php $resp = $payment->gateway_response; @endphp
                @if($payment->payment_method === 'pix' && ($pixCode = $resp['pix_copy_paste'] ?? $resp['copy_paste'] ?? $resp['pix']['copy_paste'] ?? null))
                    <p class="text-gray-600 mb-4">Copie o código PIX abaixo e cole no app do seu banco para pagar:</p>
                    <div class="bg-gray-100 rounded-lg p-4 mb-4 text-left">
                        <code id="pix-code" class="text-sm break-all select-all">{{ is_array($pixCode) ? ($pixCode['copy_paste'] ?? json_encode($pixCode)) : $pixCode }}</code>
                    </div>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('pix-code').innerText); this.textContent='Copiado!'; setTimeout(() => this.textContent='Copiar código', 2000)"
                            class="mb-4 inline-block bg-green-600 text-white font-semibold px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm">
                        Copiar código
                    </button>
                @elseif($payment->payment_method === 'boleto' && ($boletoUrl = $payment->payment_url ?? $resp['boleto_url'] ?? $resp['url'] ?? null))
                    <p class="text-gray-600 mb-4">Clique no botão abaixo para visualizar e pagar o boleto:</p>
                    <a href="{{ $boletoUrl }}" target="_blank" rel="noopener"
                       class="inline-block bg-green-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-green-700 transition mb-4">
                        Ver boleto
                    </a>
                @else
                    <p class="text-gray-500 mb-6">Seu pagamento ainda não foi confirmado. Se você já realizou o pagamento, aguarde alguns instantes e atualize esta página.</p>
                @endif
            @else
                <p class="text-gray-500 mb-6">Seu pagamento ainda não foi confirmado. Se você já realizou o pagamento, aguarde alguns instantes e atualize esta página.</p>
            @endif

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
