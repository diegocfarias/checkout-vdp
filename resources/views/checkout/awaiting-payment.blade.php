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

            @if(isset($payment) && $payment->gateway_response)
                @php
                    $resp = $payment->gateway_response;
                    $paymentData = $resp['payment'] ?? $resp;
                    $pixCode = $paymentData['pix_emv'] ?? $paymentData['copy_paste'] ?? $resp['pix_copy_paste'] ?? $payment->payment_url ?? null;
                    $pixQrCode = $paymentData['pix_qrcode'] ?? null;
                    $boletoUrl = $paymentData['boleto_url'] ?? $resp['boleto_url'] ?? $payment->payment_url ?? null;
                @endphp
                @if($payment->payment_method === 'pix' && $pixCode && !str_starts_with($pixCode, 'http'))
                    <p class="text-gray-600 mb-4">Escaneie o QR Code ou copie o código PIX abaixo:</p>
                    @if($pixQrCode)
                        <div class="mb-4 flex justify-center">
                            <img src="data:image/png;base64,{{ $pixQrCode }}" alt="QR Code PIX" class="w-48 h-48 rounded-lg border border-gray-200">
                        </div>
                    @endif
                    <div class="bg-gray-100 rounded-lg p-4 mb-4 text-left">
                        <code id="pix-code" class="text-sm break-all select-all">{{ $pixCode }}</code>
                    </div>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('pix-code').innerText); this.textContent='Copiado!'; setTimeout(() => this.textContent='Copiar código PIX', 2000)"
                            class="mb-4 inline-block bg-green-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-green-700 transition">
                        Copiar código PIX
                    </button>
                @elseif($payment->payment_method === 'boleto' && $boletoUrl && str_starts_with($boletoUrl, 'http'))
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

            <button type="button" id="btn-verificar"
                    class="inline-block bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-blue-700 transition cursor-pointer">
                Verificar pagamento
            </button>

            <p class="text-xs text-gray-400 mt-4" id="status-msg">Verificando automaticamente a cada 5 segundos...</p>

            @if($order->expires_at)
                <p class="text-xs text-gray-400 mt-2">
                    Link expira em {{ $order->expires_at->format('d/m/Y H:i') }}
                </p>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        (function() {
            const callbackUrl = @json(route('checkout.payment-callback', $order->token));
            let attempts = 0;
            const maxAttempts = 120;
            let checking = false;

            function checkPayment() {
                if (checking) return;
                if (attempts >= maxAttempts) {
                    document.getElementById('status-msg').textContent = 'Tempo de verificação esgotado. Clique em "Verificar pagamento".';
                    return;
                }
                checking = true;
                attempts++;

                var btn = document.getElementById('btn-verificar');
                if (btn) btn.disabled = true;

                fetch(callbackUrl, { redirect: 'follow', credentials: 'same-origin' })
                    .then(function(resp) {
                        if (resp.redirected) {
                            window.location.href = resp.url;
                            return null;
                        }
                        return resp.text();
                    })
                    .then(function(html) {
                        checking = false;
                        if (btn) btn.disabled = false;
                        if (html === null) return;
                        if (!html) { setTimeout(checkPayment, 5000); return; }
                        if (html.includes('tracking_code') || html.includes('Acompanhar pedido')) {
                            window.location.href = callbackUrl;
                            return;
                        }
                        setTimeout(checkPayment, 5000);
                    })
                    .catch(function() {
                        checking = false;
                        if (btn) btn.disabled = false;
                        setTimeout(checkPayment, 5000);
                    });
            }

            document.getElementById('btn-verificar').addEventListener('click', function() {
                document.getElementById('status-msg').textContent = 'Verificando...';
                checkPayment();
            });

            setTimeout(checkPayment, 5000);
        })();
    </script>
    @endpush
@endsection
