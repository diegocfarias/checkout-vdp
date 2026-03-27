@extends('layouts.public')

@section('title', 'Meus Pedidos')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Meus pedidos</h1>
                <p class="text-sm text-gray-500">Acompanhe todos os seus pedidos.</p>
            </div>
            <a href="{{ route('customer.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">Voltar</a>
        </div>

        @if($orders->isNotEmpty())
            <div class="space-y-3">
                @foreach($orders as $order)
                    @php
                        $statusColors = [
                            'pending' => 'bg-amber-100 text-amber-700',
                            'awaiting_payment' => 'bg-blue-100 text-blue-700',
                            'awaiting_emission' => 'bg-purple-100 text-purple-700',
                            'completed' => 'bg-emerald-100 text-emerald-700',
                            'cancelled' => 'bg-red-100 text-red-700',
                        ];
                        $statusLabels = [
                            'pending' => 'Pendente',
                            'awaiting_payment' => 'Aguardando pagamento',
                            'awaiting_emission' => 'Aguardando emissão',
                            'completed' => 'Concluído',
                            'cancelled' => 'Cancelado',
                        ];
                        $total = $order->flights->sum(fn($f) => (float)($f->money_price ?? 0) + (float)($f->tax ?? 0));
                    @endphp
                    <a href="{{ route('customer.order.show', $order) }}" class="block bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-emerald-300 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-mono font-semibold text-gray-500 bg-gray-100 px-2 py-1 rounded">{{ $order->tracking_code }}</span>
                            <span class="text-xs px-2 py-1 rounded-full font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $statusLabels[$order->status] ?? $order->status }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">{{ strtoupper($order->departure_iata) }} → {{ strtoupper($order->arrival_iata) }}</span>
                            <span class="text-sm text-gray-500">R$ {{ number_format($total, 2, ',', '.') }}</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $orders->links() }}
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                <p class="text-gray-500 mb-3">Nenhum pedido encontrado.</p>
                <a href="{{ route('search.home') }}" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">Buscar passagens</a>
            </div>
        @endif
    </div>
@endsection
