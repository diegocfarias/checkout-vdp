@extends('layouts.public')

@section('title', 'Pedido ' . $order->tracking_code)

@section('content')
    <div class="max-w-3xl mx-auto">
        <a href="{{ route('customer.orders') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Voltar aos pedidos
        </a>

        @php
            $statusLabels = [
                'pending' => 'Pendente',
                'awaiting_payment' => 'Aguardando pagamento',
                'awaiting_emission' => 'Aguardando emissão',
                'completed' => 'Concluído',
                'cancelled' => 'Cancelado',
            ];
            $statusColors = [
                'pending' => 'bg-amber-100 text-amber-700',
                'awaiting_payment' => 'bg-blue-100 text-blue-700',
                'awaiting_emission' => 'bg-purple-100 text-purple-700',
                'completed' => 'bg-emerald-100 text-emerald-700',
                'cancelled' => 'bg-red-100 text-red-700',
            ];
        @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Pedido {{ $order->tracking_code }}</h1>
                    <p class="text-xs text-gray-400">Criado em {{ $order->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <span class="text-xs px-3 py-1.5 rounded-full font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ $statusLabels[$order->status] ?? $order->status }}
                </span>
            </div>

            <div class="text-sm text-gray-700">
                <p><strong>Rota:</strong> {{ strtoupper($order->departure_iata) }} → {{ strtoupper($order->arrival_iata) }}</p>
                <p><strong>Cabine:</strong> {{ ucfirst($order->cabin) }}</p>
            </div>
        </div>

        @if($order->flights->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
                <h3 class="font-semibold text-gray-800 mb-3">Voos</h3>
                @foreach($order->flights as $flight)
                    <div class="p-3 rounded-lg bg-gray-50 mb-2 last:mb-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded {{ $flight->direction === 'outbound' ? 'bg-slate-200 text-slate-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $flight->direction === 'outbound' ? 'IDA' : 'VOLTA' }}
                            </span>
                            <span class="text-xs text-gray-500 uppercase">{{ $flight->cia }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span>{{ $flight->departure_location }} → {{ $flight->arrival_location }}</span>
                            <span class="text-gray-500">{{ $flight->departure_time }} - {{ $flight->arrival_time }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($order->passengers->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
                <h3 class="font-semibold text-gray-800 mb-3">Passageiros</h3>
                @foreach($order->passengers as $passenger)
                    <div class="p-3 rounded-lg bg-gray-50 mb-2 last:mb-0 text-sm">
                        <p class="font-medium text-gray-700">{{ $passenger->full_name }}</p>
                        <p class="text-gray-500">{{ $passenger->email }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        @php
            $total = $order->flights->sum(fn($f) => (float)($f->money_price ?? 0) + (float)($f->tax ?? 0));
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex justify-between items-center">
                <span class="font-semibold text-gray-800">Total</span>
                <span class="text-xl font-bold text-gray-900">R$ {{ number_format($total, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>
@endsection
