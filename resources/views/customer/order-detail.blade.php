@extends('layouts.public')

@section('title', 'Pedido ' . $order->tracking_code)

@section('content')
    <div class="max-w-4xl mx-auto">
        <a href="{{ route('customer.orders') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Voltar aos pedidos
        </a>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Pedido {{ $order->tracking_code }}</h1>
                    <p class="text-xs text-gray-400">Criado em {{ $order->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <span class="text-xs px-3 py-1.5 rounded-full font-medium {{ $order->displayStatusBadgeClasses() }}">
                    {{ $order->displayStatusLabel() }}
                </span>
            </div>

            <div class="text-sm text-gray-700">
                <p><strong>Rota:</strong> {{ strtoupper($order->departure_iata) }} → {{ strtoupper($order->arrival_iata) }}</p>
                <p><strong>Cabine:</strong> {{ ucfirst($order->cabin) }}</p>
            </div>
        </div>

        {{-- PIX pendente --}}
        @php
            $pixPayment = $order->payments->first(fn($p) => $p->payment_method === 'pix' && $p->status === 'pending');
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

        @if($pixPayment && $pixCode && in_array($order->status, ['awaiting_payment', 'pending']))
            <div class="bg-white rounded-xl shadow-sm border {{ $pixExpired ? 'border-red-200' : 'border-emerald-200' }} p-5 mb-4">
                @if($pixExpired)
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3 class="font-semibold text-red-700">PIX expirado</h3>
                    </div>
                    <p class="text-sm text-gray-500">O tempo para pagamento via PIX expirou. Solicite um novo link de pagamento.</p>
                @else
                    <div class="flex items-center justify-between mb-3">
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

        @if($order->flights->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
                <h3 class="font-semibold text-gray-800 mb-3">Voos</h3>
                @foreach($order->flights as $flight)
                    @php
                        $flightDate = null;
                        if ($order->flightSearch) {
                            $flightDate = $flight->direction === 'outbound'
                                ? $order->flightSearch->outbound_date
                                : $order->flightSearch->inbound_date;
                        }
                    @endphp
                    <div class="p-3 rounded-lg bg-gray-50 mb-2 last:mb-0">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                    {{ $flight->direction === 'outbound' ? 'IDA' : 'VOLTA' }}
                                </span>
                                <span class="text-xs text-gray-500 uppercase">{{ $flight->cia }}</span>
                                @if($flight->flight_number)
                                    <span class="text-xs text-gray-400">{{ $flight->flight_number }}</span>
                                @endif
                                @include('partials._baggage_icons', ['baggage' => $flight->baggage, 'class' => 'flex items-center gap-1.5 shrink-0'])
                            </div>
                            @if($flightDate)
                                <span class="text-xs font-medium text-gray-600">{{ $flightDate->format('d/m/Y') }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <p class="font-medium text-gray-800">{{ $flight->departure_location }}</p>
                                <p class="text-gray-500">{{ $flight->departure_time }}</p>
                            </div>
                            <div class="flex-1 mx-3 text-center">
                                @if($flight->total_flight_duration)
                                    <span class="text-gray-400 text-xs">{{ $flight->total_flight_duration }}</span>
                                @endif
                                <div class="border-t border-gray-300 mt-1"></div>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-800">{{ $flight->arrival_location }}</p>
                                <p class="text-gray-500">{{ $flight->arrival_time }}</p>
                            </div>
                        </div>
                        @if($order->status === 'completed' && $flight->loc)
                            <div class="mt-2">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                    LOC: {{ $flight->loc }}
                                </span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if($order->passengers->isNotEmpty())
            @php
                $natLabelsOd = [
                    'BR' => 'Brasil', 'AR' => 'Argentina', 'UY' => 'Uruguai', 'PY' => 'Paraguai',
                    'CL' => 'Chile', 'CO' => 'Colômbia', 'PE' => 'Peru', 'BO' => 'Bolívia',
                    'EC' => 'Equador', 'VE' => 'Venezuela', 'US' => 'Estados Unidos', 'PT' => 'Portugal',
                    'ES' => 'Espanha', 'IT' => 'Itália', 'DE' => 'Alemanha', 'FR' => 'França',
                    'GB' => 'Reino Unido', 'JP' => 'Japão', 'XX' => 'Outro',
                ];
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
                <h3 class="font-semibold text-gray-800 mb-3">Passageiros</h3>
                @foreach($order->passengers as $passenger)
                    <div class="p-3 rounded-lg bg-gray-50 mb-2 last:mb-0 text-sm">
                        <p class="font-medium text-gray-700">{{ $passenger->full_name }}</p>
                        <p class="text-gray-500">{{ $natLabelsOd[$passenger->nationality ?? 'BR'] ?? $passenger->nationality }}</p>
                        @if($passenger->document)
                            @php
                                $odDoc = preg_replace('/\D/', '', $passenger->document);
                                $odDocFmt = strlen($odDoc) === 11
                                    ? substr($odDoc, 0, 3) . '.' . substr($odDoc, 3, 3) . '.' . substr($odDoc, 6, 3) . '-' . substr($odDoc, 9, 2)
                                    : $passenger->document;
                            @endphp
                            <p class="text-gray-500">CPF: {{ $odDocFmt }}</p>
                        @endif
                        @if($passenger->passport_number)
                            <p class="text-gray-500">Passaporte: {{ $passenger->passport_number }}</p>
                        @endif
                        @if($passenger->passport_expiry)
                            <p class="text-gray-500">Validade: {{ $passenger->passport_expiry->format('d/m/Y') }}</p>
                        @endif
                        <p class="text-gray-500">{{ $passenger->email }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        @php
            $openCancellationTicket = $order->supportTickets
                ->first(fn($ticket) => $ticket->subject === 'cancellation' && $ticket->is_open);
        @endphp

        <div class="mb-4">
            @include('partials._cancellation_policy_summary', ['compact' => true])
        </div>

        {{-- Solicitação de cancelamento --}}
        <div class="bg-white rounded-xl shadow-sm border {{ ($cancellationEvaluation['within_policy'] ?? false) ? 'border-amber-300' : 'border-gray-200' }} p-5 mb-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="font-semibold text-gray-800 text-sm">Solicitar cancelamento</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $cancellationEvaluation['rule'] ?? 'Nossa equipe vai analisar as regras da companhia e fornecedor antes de efetivar.' }}
                    </p>
                </div>
                @if($cancellationEvaluation['within_policy'] ?? false)
                    <span class="inline-flex w-fit items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                        Prioridade
                    </span>
                @endif
            </div>

            @if($openCancellationTicket)
                <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                    Já existe uma solicitação de cancelamento aberta para este pedido:
                    <a href="{{ route('customer.support.show', $openCancellationTicket) }}" class="font-semibold underline">acompanhar atendimento #{{ $openCancellationTicket->id }}</a>.
                </div>
            @else
                @if($errors->any())
                    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        Confira os campos da solicitação de cancelamento.
                    </div>
                @endif

                <form method="POST" action="{{ route('customer.order.cancellation.store', $order) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="cancellation_reason" class="block text-sm font-medium text-gray-700 mb-1.5">Motivo</label>
                        <select id="cancellation_reason" name="reason" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Selecione...</option>
                            @foreach($cancellationReasons as $key => $label)
                                <option value="{{ $key }}" @selected(old('reason') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reason')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="cancellation_message" class="block text-sm font-medium text-gray-700 mb-1.5">Detalhes <span class="font-normal text-gray-400">(opcional)</span></label>
                        <textarea id="cancellation_message" name="message" rows="4" maxlength="5000" placeholder="Conte se deseja cancelar todos os passageiros/trechos ou informe qualquer detalhe importante."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Anexos</label>
                        <input type="file" name="attachments[]" multiple
                            class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-1">Até 5 arquivos de 10 MB.</p>
                        @error('attachments')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                        @error('attachments.*')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="w-full sm:w-auto bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-5 rounded-lg transition-colors text-sm">
                        Abrir solicitação de cancelamento
                    </button>
                </form>
            @endif
        </div>

        {{-- Botão Atendimento --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-800 text-sm">Precisa de ajuda?</h3>
                    <p class="text-xs text-gray-500">Abra uma solicitação sobre este pedido.</p>
                </div>
                <button type="button" onclick="document.getElementById('support-modal').classList.remove('hidden')"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    Abrir atendimento
                </button>
            </div>
        </div>

        {{-- Modal de Atendimento --}}
        <div id="support-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('support-modal').classList.add('hidden')"></div>
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10 max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-800">Abrir atendimento</h2>
                        <button type="button" onclick="document.getElementById('support-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('customer.support.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $order->id }}">

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Assunto</label>
                            <select name="subject" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Selecione...</option>
                                @foreach(\App\Models\SupportTicket::SUBJECTS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Mensagem</label>
                            <textarea name="message" rows="5" required maxlength="5000" placeholder="Descreva o que precisa..."
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Anexos</label>
                            <input type="file" name="attachments[]" multiple
                                class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                            <p class="text-xs text-gray-400 mt-1">Até 5 arquivos de 10 MB.</p>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-colors text-sm">
                            Enviar solicitação
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @php
            $payingPax = $order->total_adults + $order->total_children;
            if ($payingPax < 1) $payingPax = 1;
            $totalPerPax = $order->flights->sum(fn($f) => (float)($f->money_price ?? 0) + (float)($f->tax ?? 0));
            $total = $totalPerPax * $payingPax;
            $finalTotal = $total - (float)($order->discount_amount ?? 0) - (float)($order->wallet_amount_used ?? 0);
            $hasAnyDiscount = ($order->discount_amount ?? 0) > 0 || ($order->wallet_amount_used ?? 0) > 0;
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-2">
            @if($hasAnyDiscount)
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Subtotal</span>
                    <span>R$ {{ number_format($total, 2, ',', '.') }}</span>
                </div>
            @endif
            @if($order->discount_amount > 0 && $order->coupon)
                <div class="flex justify-between text-sm text-emerald-600">
                    <span class="flex items-center gap-1.5">
                        Cupom
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-700">{{ $order->coupon->code }}</span>
                        <span class="text-emerald-500 text-xs">
                            ({{ $order->coupon->type === 'percent' ? $order->coupon->value . '%' : 'R$ ' . number_format($order->coupon->value, 2, ',', '.') }})
                        </span>
                    </span>
                    <span class="font-medium">- R$ {{ number_format($order->discount_amount, 2, ',', '.') }}</span>
                </div>
            @elseif($order->discount_amount > 0 && $order->referral_id)
                <div class="flex justify-between text-sm text-emerald-600">
                    <span>Desconto indicação</span>
                    <span class="font-medium">- R$ {{ number_format($order->discount_amount, 2, ',', '.') }}</span>
                </div>
            @endif
            @if(($order->wallet_amount_used ?? 0) > 0)
                <div class="flex justify-between text-sm text-emerald-600">
                    <span>Crédito utilizado</span>
                    <span class="font-medium">- R$ {{ number_format($order->wallet_amount_used, 2, ',', '.') }}</span>
                </div>
            @endif
            <div class="flex justify-between items-center {{ $hasAnyDiscount ? 'pt-2 border-t border-gray-100' : '' }}">
                <span class="font-semibold text-gray-800">Total</span>
                <span class="text-xl font-bold text-gray-900">R$ {{ number_format($finalTotal, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>
@endsection

@if($pixPayment && $pixCode && in_array($order->status, ['awaiting_payment', 'pending']) && !$pixExpired)
@push('scripts')
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
                btnCopy.classList.add('bg-gray-600', 'hover:bg-gray-700');
                setTimeout(function() {
                    copyText.textContent = 'Copiar código PIX';
                    btnCopy.classList.remove('bg-gray-600', 'hover:bg-gray-700');
                    btnCopy.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2500);
            });
        });
    }

    @if($pixPayment->expires_at)
    var expiresAt = new Date(@json($pixPayment->expires_at->toIso8601String()));
    var countdownEl = document.getElementById('pix-countdown');

    function updateCountdown() {
        var now = new Date();
        var diff = expiresAt - now;

        if (diff <= 0) {
            if (countdownEl) countdownEl.textContent = '00:00';
            setTimeout(function() { window.location.reload(); }, 2000);
            return;
        }

        var minutes = Math.floor(diff / 60000);
        var seconds = Math.floor((diff % 60000) / 1000);
        var display = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

        if (countdownEl) countdownEl.textContent = display;

        if (diff < 120000) {
            if (countdownEl) countdownEl.classList.remove('text-amber-600');
            if (countdownEl) countdownEl.classList.add('text-red-600');
        }

        setTimeout(updateCountdown, 1000);
    }
    updateCountdown();
    @endif

    var statusMsg = document.getElementById('pix-status-msg');
    var attempts = 0;
    var maxAttempts = 120;

    function checkPayment() {
        if (attempts >= maxAttempts) {
            if (statusMsg) statusMsg.textContent = 'Tempo de verificação esgotado. Recarregue a página.';
            return;
        }
        attempts++;
        fetch(window.location.href, { credentials: 'same-origin' })
            .then(function(resp) { return resp.text(); })
            .then(function(html) {
                if (html.includes('Aguardando emissão') || html.includes('Concluído') || !html.includes('pix-code')) {
                    if (statusMsg) statusMsg.textContent = 'Pagamento confirmado! Recarregando...';
                    setTimeout(function() { window.location.reload(); }, 1000);
                    return;
                }
                if (html.includes('PIX expirado')) {
                    setTimeout(function() { window.location.reload(); }, 1000);
                    return;
                }
                setTimeout(checkPayment, 5000);
            })
            .catch(function() {
                setTimeout(checkPayment, 5000);
            });
    }

    setTimeout(checkPayment, 5000);
})();
</script>
@endpush
@endif
