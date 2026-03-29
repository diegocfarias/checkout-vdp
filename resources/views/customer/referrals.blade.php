@extends('layouts.public')

@section('title', 'Minhas Indicações')

@section('content')
    <div class="max-w-4xl mx-auto">
        <a href="{{ route('customer.dashboard') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Voltar à conta
        </a>

        <h1 class="text-2xl font-bold text-gray-800 mb-6">Indique e Ganhe</h1>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
            <h3 class="font-semibold text-gray-800 mb-3">Seu código de indicação</h3>
            <div class="flex items-center gap-3 mb-4">
                <div class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                    <span class="text-lg font-bold text-blue-600 font-mono tracking-wider" id="ref-code">{{ $customer->referral_code }}</span>
                </div>
                <button type="button" onclick="copyText('{{ $customer->referral_code }}', this)"
                    class="px-4 py-3 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors shrink-0">
                    Copiar código
                </button>
            </div>

            <h3 class="font-semibold text-gray-800 mb-2 text-sm">Link de indicação</h3>
            <div class="flex items-center gap-3">
                <div class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 overflow-hidden">
                    <span class="text-sm text-gray-600 break-all" id="ref-link">{{ $referralLink }}</span>
                </div>
                <button type="button" onclick="copyText('{{ $referralLink }}', this)"
                    class="px-4 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors shrink-0">
                    Copiar link
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-2">Compartilhe este link. Quem comprar usando seu código recebe desconto e você ganha créditos!</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5">
                <p class="text-sm text-gray-500 mb-1">Saldo disponível</p>
                <p class="text-2xl font-bold text-emerald-600">R$ {{ number_format($availableBalance, 2, ',', '.') }}</p>
                <p class="text-xs text-gray-400 mt-1">Use no checkout como forma de pagamento</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-amber-200 p-5">
                <p class="text-sm text-gray-500 mb-1">Saldo pendente</p>
                <p class="text-2xl font-bold text-amber-600">R$ {{ number_format($pendingBalance, 2, ',', '.') }}</p>
                <p class="text-xs text-gray-400 mt-1">Será liberado automaticamente após o prazo</p>
            </div>
        </div>

        @if($referrals->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
            <h3 class="font-semibold text-gray-800 mb-3">Histórico de indicações</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 px-2 text-gray-500 font-medium">Pedido</th>
                            <th class="text-right py-2 px-2 text-gray-500 font-medium">Valor</th>
                            <th class="text-right py-2 px-2 text-gray-500 font-medium">Crédito</th>
                            <th class="text-center py-2 px-2 text-gray-500 font-medium">Status</th>
                            <th class="text-right py-2 px-2 text-gray-500 font-medium">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($referrals as $ref)
                            @php
                                $statusLabel = match($ref->credit_status) {
                                    'pending' => 'Pendente',
                                    'available' => 'Disponível',
                                    'used' => 'Usado',
                                    'reversed' => 'Revertido',
                                    default => ucfirst($ref->credit_status),
                                };
                                $statusColor = match($ref->credit_status) {
                                    'pending' => 'bg-amber-100 text-amber-700',
                                    'available' => 'bg-emerald-100 text-emerald-700',
                                    'used' => 'bg-blue-100 text-blue-700',
                                    'reversed' => 'bg-red-100 text-red-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <tr>
                                <td class="py-2.5 px-2">
                                    <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $ref->referredOrder?->tracking_code ?? '-' }}</span>
                                </td>
                                <td class="py-2.5 px-2 text-right text-gray-600">R$ {{ number_format($ref->order_base_total, 2, ',', '.') }}</td>
                                <td class="py-2.5 px-2 text-right font-semibold text-gray-800">R$ {{ number_format($ref->credit_amount, 2, ',', '.') }}</td>
                                <td class="py-2.5 px-2 text-center">
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $statusColor }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="py-2.5 px-2 text-right text-gray-500">{{ $ref->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $referrals->links() }}</div>
        </div>
        @endif

        @if($walletHistory->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Extrato da carteira</h3>
            <div class="space-y-2">
                @foreach($walletHistory as $tx)
                    @php
                        $isPositive = $tx->type === 'credit';
                        $sign = $isPositive ? '+' : '-';
                        $color = $isPositive ? 'text-emerald-600' : 'text-red-600';
                    @endphp
                    <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                        <div>
                            <p class="text-sm text-gray-700">{{ $tx->description }}</p>
                            <p class="text-xs text-gray-400">{{ $tx->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold {{ $color }}">{{ $sign }} R$ {{ number_format($tx->amount, 2, ',', '.') }}</p>
                            <p class="text-xs text-gray-400">Saldo: R$ {{ number_format($tx->balance_after, 2, ',', '.') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <script>
        function copyText(text, btn) {
            navigator.clipboard.writeText(text).then(function () {
                var original = btn.textContent;
                btn.textContent = 'Copiado!';
                btn.classList.remove('bg-blue-600', 'bg-gray-800', 'hover:bg-blue-700', 'hover:bg-gray-700');
                btn.classList.add('bg-emerald-600');
                setTimeout(function () {
                    btn.textContent = original;
                    btn.classList.remove('bg-emerald-600');
                    if (original.includes('código')) {
                        btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    } else {
                        btn.classList.add('bg-gray-800', 'hover:bg-gray-700');
                    }
                }, 2000);
            });
        }
    </script>
@endsection
