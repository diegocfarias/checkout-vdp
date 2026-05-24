@extends('layouts.public')

@section('title', 'Política de cancelamento')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Atendimento ao cliente</p>
            <h1 class="mt-2 text-3xl font-bold text-gray-900">Política de cancelamento e reembolso</h1>
            <p class="mt-3 text-sm leading-6 text-gray-500">
                Estas regras orientam a abertura e análise de solicitações. A confirmação final depende da companhia aérea,
                tarifa, fornecedor de emissão, gateway de pagamento e documentação do pedido.
            </p>
        </div>

        @include('partials._cancellation_policy_summary')

        <div class="grid gap-4 md:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Cancelamento sem custo</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Pode ser solicitado quando a compra/pagamento foi confirmado há até 24 horas e o primeiro embarque
                    está a 7 dias ou mais. Nessa situação, o reembolso esperado é de 100% do valor efetivamente pago,
                    incluindo taxas.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Pedido sem pagamento</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Pedido pendente ou aguardando pagamento pode ser cancelado sem multa. Se houver crédito Wallet
                    reservado ou debitado, ele deve ser liberado ou devolvido uma única vez.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Fora da janela sem custo</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Seguimos as regras da companhia, tarifa, integração e fornecedor. Antes de efetivar, nossa equipe
                    informa multa, valores reembolsáveis, prazo estimado e solicita aceite do cliente.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Cancelamento involuntário</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Quando o voo é cancelado, sofre alteração relevante, há falha operacional ou indisponibilidade não
                    causada pelo cliente, oferecemos reembolso integral ou alternativa/remarcação sem taxa interna VDP.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Taxas e descontos</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Taxas de embarque e valores governamentais são tratados separadamente da passagem. Cupom, Pix,
                    indicação e promoções reduzem o valor pago, mas não geram crédito em dinheiro no cancelamento.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">No-show e cancelamento parcial</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Não comparecer ao embarque sem solicitação prévia não garante reembolso da tarifa. Cancelamento por
                    trecho ou passageiro só é possível quando a companhia/fornecedor permitir e o cálculo for auditável.
                </p>
            </section>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50 p-5">
            <h2 class="text-lg font-semibold text-blue-950">Como solicitar</h2>
            <p class="mt-2 text-sm leading-6 text-blue-900">
                Acesse seu pedido em "Minha conta" ou "Meu pedido", escolha o motivo do cancelamento e descreva os
                detalhes. Solicitações dentro das regras prioritárias chegam destacadas para nossa equipe de atendimento.
            </p>
        </div>
    </div>
@endsection
