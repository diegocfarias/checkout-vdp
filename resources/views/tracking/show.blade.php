@extends('layouts.public')

@section('title', 'Pedido ' . $order->tracking_code)

@section('content')
    @php
        $outbound = $order->flights->firstWhere('direction', 'outbound');
        $inbound = $order->flights->firstWhere('direction', 'inbound');
        $histories = $order->statusHistories->sortByDesc('created_at');
        $currentStatus = $order->status;

        $statusConfig = [
            'pending' => ['label' => 'Pedido criado', 'color' => 'gray', 'bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400'],
            'awaiting_payment' => ['label' => 'Aguardando pagamento', 'color' => 'yellow', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'dot' => 'bg-yellow-500'],
            'awaiting_emission' => ['label' => 'Pagamento confirmado', 'color' => 'blue', 'bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'dot' => 'bg-blue-500'],
            'completed' => ['label' => 'Passagens emitidas', 'color' => 'green', 'bg' => 'bg-green-100', 'text' => 'text-green-800', 'dot' => 'bg-green-500'],
            'cancelled' => ['label' => 'Cancelado', 'color' => 'red', 'bg' => 'bg-red-100', 'text' => 'text-red-800', 'dot' => 'bg-red-500'],
        ];

        $current = $statusConfig[$currentStatus] ?? $statusConfig['pending'];
    @endphp

    <div class="max-w-lg mx-auto space-y-4">
        {{-- Status atual --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm text-gray-500">Pedido</p>
                    <p class="text-xl font-bold text-gray-900">{{ $order->tracking_code }}</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold {{ $current['bg'] }} {{ $current['text'] }}">
                    <span class="w-2 h-2 rounded-full {{ $current['dot'] }}"></span>
                    {{ $current['label'] }}
                </span>
            </div>

            @if($currentStatus === 'awaiting_emission')
                <div class="bg-blue-50 rounded-lg p-3">
                    <p class="text-sm text-blue-700">Seu pagamento foi confirmado. Estamos encaminhando para emissão das passagens.</p>
                </div>
            @elseif($currentStatus === 'completed')
                <div class="bg-green-50 rounded-lg p-3">
                    <p class="text-sm text-green-700">Suas passagens foram emitidas! Confira seu e-mail para mais detalhes.</p>
                </div>
                @php
                    $flightsWithLoc = $order->flights->filter(fn ($f) => $f->loc);
                @endphp
                @if($flightsWithLoc->count() > 0)
                    <div class="mt-3 space-y-2">
                        @foreach($flightsWithLoc as $flight)
                            <div class="bg-white border-2 border-green-200 rounded-lg p-4 flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-semibold">
                                        LOC {{ $flight->direction === 'outbound' ? 'Ida' : 'Volta' }}
                                        <span class="text-gray-400">— {{ strtoupper($flight->cia) }}</span>
                                    </p>
                                    <p class="text-2xl font-bold text-gray-900 tracking-widest mt-0.5">{{ $flight->loc }}</p>
                                </div>
                                <div class="text-right text-xs text-gray-400">
                                    <p>{{ $flight->departure_location }} → {{ $flight->arrival_location }}</p>
                                </div>
                            </div>
                        @endforeach
                        <p class="text-xs text-gray-400 text-center">Use o localizador para realizar o check-in no site da companhia aérea.</p>
                    </div>
                @endif
            @elseif($currentStatus === 'awaiting_payment')
                <div class="bg-yellow-50 rounded-lg p-3">
                    <p class="text-sm text-yellow-700">Seu pagamento ainda não foi confirmado. Se você já pagou, aguarde a confirmação.</p>
                </div>
            @elseif($currentStatus === 'cancelled')
                <div class="bg-red-50 rounded-lg p-3">
                    @php $waNum = \App\Models\Setting::get('whatsapp_number'); @endphp
                    <p class="text-sm text-red-700">Este pedido foi cancelado.
                        @if($waNum)
                            Entre em contato pelo <a href="https://wa.me/{{ $waNum }}" target="_blank" class="underline font-semibold hover:text-red-900">WhatsApp</a> se precisar de ajuda.
                        @else
                            Entre em contato pelo WhatsApp se precisar de ajuda.
                        @endif
                    </p>
                </div>
            @endif
        </div>

        {{-- PIX pendente --}}
        @php
            $pixPayment = ($order->payments ?? collect())->first(fn($p) => $p->payment_method === 'pix' && $p->status === 'pending');
            $pixCode = null;
            $pixQr = null;
            $pixExpired = false;
            if ($pixPayment) {
                $pixExpired = $pixPayment->isExpired();
                if ($pixPayment->gateway_response) {
                    $resp = $pixPayment->gateway_response;
                    $pd = $resp['payment'] ?? $resp;
                    $pixCode = $pd['pix_emv'] ?? $pd['copy_paste'] ?? $resp['pix_copy_paste'] ?? $pixPayment->payment_url ?? null;
                    $pixQr = $pd['pix_qrcode'] ?? null;
                    if ($pixCode && str_starts_with($pixCode, 'http')) $pixCode = null;
                }
            }
        @endphp

        @if($pixPayment && $pixCode && in_array($currentStatus, ['awaiting_payment', 'pending']))
            <div id="pix-payment-section" class="bg-white rounded-xl shadow-sm border {{ $pixExpired ? 'border-red-200' : 'border-emerald-200' }} p-6">
                @if($pixExpired)
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3 class="font-semibold text-red-700">PIX expirado</h3>
                    </div>
                    <p class="text-sm text-gray-500">O tempo para pagamento via PIX expirou.</p>
                @else
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </div>
                            <h3 class="font-semibold text-gray-800">Pagar com PIX</h3>
                        </div>
                        @if($pixPayment->expires_at)
                            <div class="text-right">
                                <p class="text-xs text-gray-400">Expira em</p>
                                <span id="pix-countdown" class="text-sm font-bold font-mono text-amber-600"></span>
                            </div>
                        @endif
                    </div>

                    @if($pixQr)
                        <div class="hidden sm:flex justify-center mb-4">
                            <img src="data:image/png;base64,{{ $pixQr }}" alt="QR Code PIX" class="w-48 h-48 rounded-lg border border-gray-200">
                        </div>
                        <p class="hidden sm:block text-center text-xs text-gray-400 mb-4">Escaneie o QR Code com o app do seu banco</p>
                    @endif

                    <p class="text-sm text-gray-600 mb-2">Código PIX copia e cola:</p>
                    <div class="bg-gray-50 rounded-lg p-3 mb-3 border border-gray-100">
                        <code id="pix-code" class="text-xs text-gray-700 break-all select-all leading-relaxed block">{{ $pixCode }}</code>
                    </div>

                    <button type="button" id="btn-copy-pix"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                        <span id="copy-text">Copiar código PIX</span>
                    </button>

                    <p class="text-xs text-gray-400 mt-3 text-center" id="pix-status-msg">Verificando pagamento automaticamente...</p>
                @endif
            </div>
        @endif

        {{-- Voos --}}
        @if($outbound || $inbound)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Sua viagem</h3>

                @if($outbound)
                    <div class="bg-gray-50 rounded-lg p-4 mb-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="bg-slate-200 text-slate-700 text-xs font-semibold px-2 py-0.5 rounded">IDA</span>
                                <span class="text-sm text-gray-500 uppercase">{{ $outbound->cia }}</span>
                                @if($outbound->flight_number)
                                    <span class="text-sm text-gray-500">{{ $outbound->flight_number }}</span>
                                @endif
                            </div>
                            @if($order->flightSearch && $order->flightSearch->outbound_date)
                                <span class="text-xs font-medium text-gray-600">{{ $order->flightSearch->outbound_date->format('d/m/Y') }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <p class="font-medium text-gray-800">{{ $outbound->departure_location }}</p>
                                <p class="text-gray-500">{{ $outbound->departure_time }}</p>
                            </div>
                            <div class="flex-1 mx-3 text-center">
                                @if($outbound->total_flight_duration)
                                    <span class="text-gray-400 text-xs">{{ $outbound->total_flight_duration }}</span>
                                @endif
                                <div class="border-t border-gray-300 mt-1"></div>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-800">{{ $outbound->arrival_location }}</p>
                                <p class="text-gray-500">{{ $outbound->arrival_time }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if($inbound)
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="bg-slate-200 text-slate-700 text-xs font-semibold px-2 py-0.5 rounded">VOLTA</span>
                                <span class="text-sm text-gray-500 uppercase">{{ $inbound->cia }}</span>
                                @if($inbound->flight_number)
                                    <span class="text-sm text-gray-500">{{ $inbound->flight_number }}</span>
                                @endif
                            </div>
                            @if($order->flightSearch && $order->flightSearch->inbound_date)
                                <span class="text-xs font-medium text-gray-600">{{ $order->flightSearch->inbound_date->format('d/m/Y') }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <p class="font-medium text-gray-800">{{ $inbound->departure_location }}</p>
                                <p class="text-gray-500">{{ $inbound->departure_time }}</p>
                            </div>
                            <div class="flex-1 mx-3 text-center">
                                @if($inbound->total_flight_duration)
                                    <span class="text-gray-400 text-xs">{{ $inbound->total_flight_duration }}</span>
                                @endif
                                <div class="border-t border-gray-300 mt-1"></div>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-800">{{ $inbound->arrival_location }}</p>
                                <p class="text-gray-500">{{ $inbound->arrival_time }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Timeline --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-5">Histórico</h3>

            <div class="relative">
                @foreach($histories as $history)
                    @php
                        $hConfig = $statusConfig[$history->status] ?? $statusConfig['pending'];
                        $isFirst = $loop->first;
                    @endphp
                    <div class="flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
                        <div class="flex flex-col items-center">
                            <div class="w-3.5 h-3.5 rounded-full {{ $isFirst ? $hConfig['dot'] : 'bg-gray-300' }} ring-4 {{ $isFirst ? 'ring-' . $hConfig['color'] . '-100' : 'ring-gray-100' }} shrink-0 mt-0.5"></div>
                            @if(!$loop->last)
                                <div class="w-0.5 flex-1 bg-gray-200 mt-1"></div>
                            @endif
                        </div>
                        <div class="pb-1">
                            <p class="text-sm font-semibold {{ $isFirst ? 'text-gray-900' : 'text-gray-500' }}">{{ $history->description }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $history->created_at->format('d/m/Y \à\s H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Ajuda --}}
        <div class="text-center py-2">
            @php $waNum2 = \App\Models\Setting::get('whatsapp_number'); @endphp
            <p class="text-xs text-gray-400">Dúvidas?
                @if($waNum2)
                    Entre em contato pelo <a href="https://wa.me/{{ $waNum2 }}" target="_blank" class="underline hover:text-gray-600">WhatsApp</a>.
                @else
                    Entre em contato pelo WhatsApp.
                @endif
            </p>
        </div>
    </div>

    @if(isset($pixPayment) && $pixCode && !$pixExpired && in_array($currentStatus, ['awaiting_payment', 'pending']))
    <script>
        (function() {
            var btnCopy = document.getElementById('btn-copy-pix');
            var copyText = document.getElementById('copy-text');
            if (btnCopy) {
                btnCopy.addEventListener('click', function() {
                    var code = document.getElementById('pix-code').innerText;
                    navigator.clipboard.writeText(code).then(function() {
                        copyText.textContent = 'Copiado!';
                        btnCopy.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                        btnCopy.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
                        setTimeout(function() {
                            copyText.textContent = 'Copiar código PIX';
                            btnCopy.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
                            btnCopy.classList.add('bg-blue-600', 'hover:bg-blue-700');
                        }, 2000);
                    });
                });
            }

            var callbackUrl = @json(route('checkout.payment-callback', $order->token));
            var attempts = 0;
            var maxAttempts = 120;
            var autoTimer = null;
            var autoChecking = false;
            var statusMsg = document.getElementById('pix-status-msg');

            function showPixExpired() {
                var pixSection = document.getElementById('pix-payment-section');
                if (pixSection) {
                    pixSection.innerHTML =
                        '<div class="text-center py-4">' +
                            '<p class="text-red-600 font-semibold">PIX expirado</p>' +
                            '<p class="text-sm text-gray-500 mt-1">O tempo para pagamento via PIX expirou.</p>' +
                        '</div>';
                }
            }

            @if($pixPayment->expires_at)
            var expiresAt = new Date(@json($pixPayment->expires_at->toIso8601String()));
            var countdownEl = document.getElementById('pix-countdown');

            function updateCountdown() {
                var now = new Date();
                var diff = expiresAt - now;
                if (diff <= 0) {
                    if (countdownEl) countdownEl.textContent = '00:00';
                    if (countdownEl) countdownEl.classList.replace('text-amber-600', 'text-red-600');
                    clearTimeout(autoTimer);
                    setTimeout(showPixExpired, 2000);
                    return;
                }
                var minutes = Math.floor(diff / 60000);
                var seconds = Math.floor((diff % 60000) / 1000);
                if (countdownEl) countdownEl.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                if (diff < 120000 && countdownEl) countdownEl.classList.replace('text-amber-600', 'text-red-600');
                setTimeout(updateCountdown, 1000);
            }
            updateCountdown();
            @endif

            function checkPayment() {
                if (autoChecking) return;
                if (attempts >= maxAttempts) return;
                autoChecking = true;
                attempts++;

                fetch(callbackUrl, { redirect: 'follow', credentials: 'same-origin' })
                    .then(function(resp) {
                        if (resp.redirected) { window.location.href = resp.url; return null; }
                        return resp.text();
                    })
                    .then(function(html) {
                        autoChecking = false;
                        if (html === null) return;
                        if (html && (html.includes('Pagamento confirmado') || html.includes('awaiting_emission') || html.includes('completed'))) {
                            window.location.reload();
                            return;
                        }
                        if (html && (html.includes('Pagamento expirado') || html.includes('cancelado'))) {
                            showPixExpired();
                            return;
                        }
                        scheduleAutoCheck();
                    })
                    .catch(function() {
                        autoChecking = false;
                        scheduleAutoCheck();
                    });
            }

            function scheduleAutoCheck() {
                clearTimeout(autoTimer);
                autoTimer = setTimeout(checkPayment, 5000);
            }

            scheduleAutoCheck();
        })();
    </script>
    @endif
@endsection
