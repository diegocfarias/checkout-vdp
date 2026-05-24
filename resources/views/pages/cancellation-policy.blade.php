@extends('layouts.public')

@section('title', 'Política de cancelamento')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Atendimento ao cliente</p>
            <h1 class="mt-2 text-3xl font-bold text-gray-900">Política de cancelamento e reembolso</h1>
            <p class="mt-3 text-sm leading-6 text-gray-500">
                A gente sabe que planos podem mudar. Para evitar surpresa, deixamos a regra bem clara antes da compra:
                existe um prazo para cancelar sem custo. Fora dele, cancelamentos voluntários não geram reembolso.
            </p>
        </div>

        @include('partials._cancellation_policy_summary')

        <div class="grid gap-4 md:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Quando cancelamos sem custo</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Você pode pedir cancelamento sem custo se a solicitação for feita em até 24 horas depois da
                    compra/pagamento e o primeiro voo estiver a 7 dias ou mais. Nesse caso, devolvemos o valor
                    efetivamente pago.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Pedido sem pagamento</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Se o pedido ainda está pendente ou aguardando pagamento, ele pode ser cancelado sem multa e sem
                    estorno externo. Se algum crédito da carteira tiver sido reservado, ele volta para sua carteira.
                </p>
            </section>

            <section class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-red-950">Fora do prazo</h2>
                <p class="mt-2 text-sm leading-6 text-red-900">
                    Depois da janela de 24 horas, ou quando o primeiro voo está a menos de 7 dias, o cancelamento
                    voluntário não tem reembolso. Ainda assim, você pode registrar a solicitação para que nossa equipe
                    acompanhe o encerramento do pedido.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Quando a companhia muda o voo</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Se o voo for cancelado ou sofrer alteração relevante pela companhia aérea, o caso é tratado
                    separadamente. Nossa equipe verifica as alternativas disponíveis e orienta você pelo atendimento.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Cupons, Pix e créditos</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Desconto no Pix, cupom, indicação ou promoção reduzem o valor pago na compra, mas não viram crédito
                    em dinheiro. O reembolso, quando aplicável, nunca passa do valor efetivamente pago.
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Não comparecimento</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">
                    Se você não embarcar e não tiver solicitado cancelamento dentro do prazo sem custo, não há reembolso
                    pela nossa política de cancelamento voluntário.
                </p>
            </section>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50 p-5">
            <h2 class="text-lg font-semibold text-blue-950">Como solicitar</h2>
            <p class="mt-2 text-sm leading-6 text-blue-900">
                Acesse seu pedido em "Minha conta", escolha o motivo e envie os detalhes. Pedidos dentro do prazo sem
                custo chegam com prioridade para a equipe. Pedidos fora do prazo ficam registrados, mas não geram
                reembolso em cancelamento voluntário.
            </p>
        </div>
    </div>
@endsection
