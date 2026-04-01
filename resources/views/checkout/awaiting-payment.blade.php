@extends('layouts.public')

@section('title', 'Aguardando Pagamento')

@section('content')
    @include('partials._checkout_stepper', ['currentStep' => 3])
    <div class="max-w-lg mx-auto text-center">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-8">
            @php
                $pixExpiresAt = $payment->expires_at ?? $order->expires_at ?? null;
                $isExpired = $pixExpiresAt && $pixExpiresAt->isPast();
            @endphp

            @if($isExpired)
                <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Pagamento expirado</h2>
                <p class="text-gray-500 mb-6">O tempo para realizar o pagamento expirou. Solicite um novo link de pagamento.</p>
            @else
                <div class="mx-auto w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>

                <h2 class="text-2xl font-bold text-gray-800 mb-2">Aguardando pagamento</h2>

                @if(isset($payment) && $payment->amount)
                    <p class="text-3xl font-bold text-emerald-600 mb-1">R$ {{ number_format($payment->amount, 2, ',', '.') }}</p>
                    <p class="text-sm text-gray-500 mb-4">via {{ $payment->payment_method === 'pix' ? 'PIX' : 'Cartão' }}</p>
                @endif

                @if($pixExpiresAt)
                    <div id="countdown-container" class="mb-4">
                        <p class="text-sm text-gray-500 mb-1">Tempo restante para pagar:</p>
                        <div id="countdown" class="text-2xl font-bold font-mono text-amber-600"></div>
                    </div>
                @endif

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
                            <div class="mb-4 flex justify-center hidden md:flex">
                                <img src="data:image/png;base64,{{ $pixQrCode }}" alt="QR Code PIX" class="w-48 h-48 rounded-lg border border-gray-200">
                            </div>
                        @endif
                        <div class="bg-gray-100 rounded-lg p-4 mb-4 text-left">
                            <code id="pix-code" class="text-sm break-all select-all">{{ $pixCode }}</code>
                        </div>
                        <button type="button" id="btn-copiar"
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
            @endif
        </div>
    </div>

    @if(!$isExpired)
    @push('scripts')
    <script>
        (function() {
            var callbackUrl = @json(route('checkout.payment-callback', $order->token));
            var attempts = 0;
            var maxAttempts = 120;
            var autoTimer = null;
            var autoChecking = false;
            var manualChecking = false;

            function showExpired() {
                var container = document.querySelector('.bg-white.rounded-lg.shadow');
                if (!container) return;
                container.innerHTML =
                    '<div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">' +
                        '<svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>' +
                        '</svg>' +
                    '</div>' +
                    '<h2 class="text-2xl font-bold text-gray-800 mb-2">Pagamento expirado</h2>' +
                    '<p class="text-gray-500 mb-6">O tempo para realizar o pagamento expirou. Solicite um novo link de pagamento.</p>';
            }

            function doCheckPayment(isManual) {
                if (isManual) {
                    if (manualChecking) return;
                    clearTimeout(autoTimer);
                    manualChecking = true;
                } else {
                    if (autoChecking || manualChecking) return;
                    autoChecking = true;
                }

                if (attempts >= maxAttempts) {
                    document.getElementById('status-msg').textContent = 'Tempo de verificação esgotado. Clique em "Verificar pagamento".';
                    autoChecking = false;
                    manualChecking = false;
                    return;
                }
                attempts++;

                var btn = document.getElementById('btn-verificar');
                if (isManual && btn) {
                    btn.disabled = true;
                    btn.textContent = 'Verificando...';
                }

                fetch(callbackUrl, { redirect: 'follow', credentials: 'same-origin' })
                    .then(function(resp) {
                        if (resp.redirected) {
                            window.location.href = resp.url;
                            return null;
                        }
                        return resp.text();
                    })
                    .then(function(html) {
                        autoChecking = false;
                        manualChecking = false;
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = 'Verificar pagamento';
                        }
                        if (html === null) return;
                        if (!html) { scheduleAutoCheck(); return; }
                        if (html.includes('tracking_code') || html.includes('Acompanhar pedido')) {
                            window.location.href = callbackUrl;
                            return;
                        }
                        if (html.includes('Pagamento expirado') || html.includes('cancelado')) {
                            showExpired();
                            return;
                        }
                        scheduleAutoCheck();
                    })
                    .catch(function() {
                        autoChecking = false;
                        manualChecking = false;
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = 'Verificar pagamento';
                        }
                        scheduleAutoCheck();
                    });
            }

            function scheduleAutoCheck() {
                clearTimeout(autoTimer);
                autoTimer = setTimeout(function() { doCheckPayment(false); }, 5000);
            }

            document.getElementById('btn-verificar').addEventListener('click', function() {
                document.getElementById('status-msg').textContent = 'Verificando...';
                doCheckPayment(true);
            });

            var btnCopiar = document.getElementById('btn-copiar');
            if (btnCopiar) {
                btnCopiar.addEventListener('click', function() {
                    navigator.clipboard.writeText(document.getElementById('pix-code').innerText);
                    btnCopiar.textContent = 'Copiado!';
                    setTimeout(function() { btnCopiar.textContent = 'Copiar código PIX'; }, 2000);
                });
            }

            @if($pixExpiresAt)
            var expiresAt = new Date(@json($pixExpiresAt->toIso8601String()));
            var countdownEl = document.getElementById('countdown');

            function updateCountdown() {
                var now = new Date();
                var diff = expiresAt - now;

                if (diff <= 0) {
                    if (countdownEl) countdownEl.textContent = '00:00';
                    if (countdownEl) countdownEl.classList.remove('text-amber-600');
                    if (countdownEl) countdownEl.classList.add('text-red-600');
                    clearTimeout(autoTimer);
                    setTimeout(showExpired, 2000);
                    return;
                }

                var minutes = Math.floor(diff / 60000);
                var seconds = Math.floor((diff % 60000) / 1000);
                if (countdownEl) countdownEl.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

                if (diff < 120000) {
                    if (countdownEl) countdownEl.classList.remove('text-amber-600');
                    if (countdownEl) countdownEl.classList.add('text-red-600');
                }

                setTimeout(updateCountdown, 1000);
            }
            updateCountdown();
            @endif

            scheduleAutoCheck();
        })();
    </script>
    @endpush
    @endif
@endsection
