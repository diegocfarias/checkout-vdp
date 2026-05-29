@php
    $compact = $compact ?? false;
@endphp

<div class="rounded-xl border border-blue-200 bg-blue-50 p-4 {{ $compact ? 'text-sm' : '' }}">
    <div class="flex items-start gap-3">
        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v14l-4-2-3 2-3-2-4 2V6a2 2 0 012-2z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-blue-950">Cancelamento da viagem</h3>
            </div>
            <p class="mt-2 text-sm leading-relaxed text-blue-900">
                Se seus planos mudarem, avise a gente o quanto antes. A regra é simples:
            </p>
            <ul class="mt-2 space-y-1.5 text-sm leading-relaxed text-blue-900">
                <li>Cancelamos sem custo quando o pedido está dentro de 24h da compra/pagamento e o primeiro voo é daqui a 7 dias ou mais.</li>
                <li>Pedido que ainda não foi pago pode ser cancelado sem cobrança.</li>
                <li>Depois desse prazo, cancelamento voluntário não gera reembolso.</li>
                <li>Se a companhia cancelar ou alterar o voo, analisamos o caso separadamente com prioridade.</li>
            </ul>
        </div>
    </div>
</div>
