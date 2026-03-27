@extends('layouts.public')

@section('title', 'Confirme sua viagem')

@section('content')
    <div class="space-y-6">
        <h2 class="text-2xl font-bold text-gray-800">Sua viagem</h2>

        @if($outbound)
            @php
                $obConns = is_array($outbound->connection) ? $outbound->connection : [];
                $obStops = max(0, count($obConns) - 1);
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <span class="bg-emerald-100 text-emerald-700 text-xs font-semibold px-2.5 py-0.5 rounded">IDA</span>
                    <span class="text-sm text-gray-600 font-medium uppercase">{{ $outbound->cia }}</span>
                    @if($outbound->flight_number)
                        <span class="text-sm text-gray-400">{{ $outbound->flight_number }}</span>
                    @endif
                    @if($order->flightSearch && $order->flightSearch->outbound_date)
                        <span class="ml-auto text-sm font-medium text-gray-700">{{ \Carbon\Carbon::parse($order->flightSearch->outbound_date)->format('d/m/Y') }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <div class="shrink-0 text-center min-w-[60px]">
                        <p class="text-xl font-bold text-gray-800">{{ $outbound->departure_time }}</p>
                        <p class="text-sm font-semibold text-gray-600">{{ $outbound->departure_location }}</p>
                    </div>
                    <div class="flex-1 px-2">
                        <div class="border-t-2 border-dashed border-gray-300 relative">
                            <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 bg-white px-2 text-[11px] text-gray-400 whitespace-nowrap">{{ $outbound->total_flight_duration ?? '' }}</span>
                        </div>
                        <p class="text-center text-[11px] mt-1 {{ $obStops > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                            {{ $obStops > 0 ? $obStops . ' conexão' : 'Direto' }}
                        </p>
                    </div>
                    <div class="shrink-0 text-center min-w-[60px]">
                        <p class="text-xl font-bold text-gray-800">{{ $outbound->arrival_time }}</p>
                        <p class="text-sm font-semibold text-gray-600">{{ $outbound->arrival_location }}</p>
                    </div>
                </div>
                @if($obStops > 0)
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-[10px] font-semibold text-gray-500 uppercase mb-1">Trechos do voo</p>
                        @include('partials._connection_details', ['segments' => $obConns, 'accentColor' => 'emerald', 'compact' => false])
                    </div>
                @endif
            </div>
        @endif

        @if($inbound)
            @php
                $ibConns = is_array($inbound->connection) ? $inbound->connection : [];
                $ibStops = max(0, count($ibConns) - 1);
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2.5 py-0.5 rounded">VOLTA</span>
                    <span class="text-sm text-gray-600 font-medium uppercase">{{ $inbound->cia }}</span>
                    @if($inbound->flight_number)
                        <span class="text-sm text-gray-400">{{ $inbound->flight_number }}</span>
                    @endif
                    @if($order->flightSearch && $order->flightSearch->inbound_date)
                        <span class="ml-auto text-sm font-medium text-gray-700">{{ \Carbon\Carbon::parse($order->flightSearch->inbound_date)->format('d/m/Y') }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <div class="shrink-0 text-center min-w-[60px]">
                        <p class="text-xl font-bold text-gray-800">{{ $inbound->departure_time }}</p>
                        <p class="text-sm font-semibold text-gray-600">{{ $inbound->departure_location }}</p>
                    </div>
                    <div class="flex-1 px-2">
                        <div class="border-t-2 border-dashed border-gray-300 relative">
                            <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 bg-white px-2 text-[11px] text-gray-400 whitespace-nowrap">{{ $inbound->total_flight_duration ?? '' }}</span>
                        </div>
                        <p class="text-center text-[11px] mt-1 {{ $ibStops > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                            {{ $ibStops > 0 ? $ibStops . ' conexão' : 'Direto' }}
                        </p>
                    </div>
                    <div class="shrink-0 text-center min-w-[60px]">
                        <p class="text-xl font-bold text-gray-800">{{ $inbound->arrival_time }}</p>
                        <p class="text-sm font-semibold text-gray-600">{{ $inbound->arrival_location }}</p>
                    </div>
                </div>
                @if($ibStops > 0)
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-[10px] font-semibold text-gray-500 uppercase mb-1">Trechos do voo</p>
                        @include('partials._connection_details', ['segments' => $ibConns, 'accentColor' => 'blue', 'compact' => false])
                    </div>
                @endif
            </div>
        @endif

        @php
            $subtotalPassagens = 0;
            $subtotalTaxas = 0;
            foreach ($order->flights ?? [] as $flight) {
                $subtotalPassagens += (float) ($flight->money_price ?? 0);
                $subtotalTaxas += (float) ($flight->tax ?? 0);
            }
            $orderTotal = $subtotalPassagens + $subtotalTaxas;
        @endphp

        <div class="pt-4 border-t border-gray-200 space-y-2">
            <div class="flex justify-between text-gray-600">
                <span>Passagens</span>
                <span>R$ {{ number_format($subtotalPassagens, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>Taxas</span>
                <span>R$ {{ number_format($subtotalTaxas, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                <span class="font-medium text-gray-700">Valor total</span>
                <span class="text-2xl font-bold text-gray-900">R$ {{ number_format($orderTotal, 2, ',', '.') }}</span>
            </div>
        </div>

        <a href="{{ route('checkout.passengers', $order->token) }}"
           class="block w-full text-center bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
            Continuar
        </a>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <a href="{{ route('search.home') }}" class="text-sm text-gray-400 hover:text-gray-600 transition-colors flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Voltar para busca
        </a>
        <p class="text-sm text-gray-400">
            Válido por {{ $order->expires_at->diffForHumans() }}
        </p>
    </div>
@endsection
