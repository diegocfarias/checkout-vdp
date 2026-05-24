@php
    $compact = $compact ?? false;
@endphp

<div class="rounded-xl border border-amber-200 bg-amber-50 p-4 {{ $compact ? 'text-sm' : '' }}">
    <div class="flex items-start gap-3">
        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v14l-4-2-3 2-3-2-4 2V6a2 2 0 012-2z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-amber-950">Regras de cancelamento</h3>
                <a href="{{ route('cancellation-policy') }}" target="_blank" class="text-xs font-semibold text-blue-700 hover:text-blue-800 hover:underline">Ver política completa</a>
            </div>
            <ul class="mt-2 space-y-1.5 text-sm leading-relaxed text-amber-900">
                <li>Cancelamento sem custo em até 24h da compra/pagamento, quando o primeiro embarque estiver a 7 dias ou mais.</li>
                <li>Pedido sem pagamento confirmado pode ser cancelado sem multa e sem estorno externo.</li>
                <li>Fora da janela sem custo, consultamos companhia/fornecedor e confirmamos multas, taxas e valor líquido antes de efetivar.</li>
                <li>Descontos, cupons, indicação e Pix não viram crédito em dinheiro; reembolso nunca passa do valor efetivamente pago.</li>
            </ul>
        </div>
    </div>
</div>
