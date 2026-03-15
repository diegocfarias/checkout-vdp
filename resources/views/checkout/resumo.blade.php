@extends('layouts.public')

@section('title', 'Confirme sua viagem')

@section('content')
    <div class="space-y-6">
        <h2 class="text-2xl font-bold text-gray-800">Sua viagem</h2>

        @if($outbound)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="bg-slate-100 text-slate-700 text-xs font-semibold px-2.5 py-0.5 rounded">IDA</span>
                    <span class="text-sm text-gray-500 uppercase">{{ $outbound->cia }}</span>
                    @if($outbound->flight_number)
                        <span class="text-sm text-gray-500">{{ $outbound->flight_number }}</span>
                    @endif
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-center">
                        <p class="text-lg font-bold text-gray-800">{{ $outbound->departure_location }}</p>
                        <p class="text-sm text-gray-500">{{ $outbound->departure_time }}</p>
                        @if($outbound->departure_label)
                            <p class="text-xs text-gray-400">{{ $outbound->departure_label }}</p>
                        @endif
                    </div>
                    <div class="flex-1 mx-4">
                        <div class="border-t-2 border-dashed border-gray-300 relative">
                            @if($outbound->total_flight_duration)
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-white px-2 text-xs text-gray-400">{{ $outbound->total_flight_duration }}</span>
                            @endif
                        </div>
                        @if(is_array($outbound->connection) && count($outbound->connection) > 0)
                            <p class="text-center text-xs text-amber-600 mt-1">{{ count($outbound->connection) }} conexão(ões)</p>
                        @endif
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-gray-800">{{ $outbound->arrival_location }}</p>
                        <p class="text-sm text-gray-500">{{ $outbound->arrival_time }}</p>
                        @if($outbound->arrival_label)
                            <p class="text-xs text-gray-400">{{ $outbound->arrival_label }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if($inbound)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="bg-slate-100 text-slate-700 text-xs font-semibold px-2.5 py-0.5 rounded">VOLTA</span>
                    <span class="text-sm text-gray-500 uppercase">{{ $inbound->cia }}</span>
                    @if($inbound->flight_number)
                        <span class="text-sm text-gray-500">{{ $inbound->flight_number }}</span>
                    @endif
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-center">
                        <p class="text-lg font-bold text-gray-800">{{ $inbound->departure_location }}</p>
                        <p class="text-sm text-gray-500">{{ $inbound->departure_time }}</p>
                        @if($inbound->departure_label)
                            <p class="text-xs text-gray-400">{{ $inbound->departure_label }}</p>
                        @endif
                    </div>
                    <div class="flex-1 mx-4">
                        <div class="border-t-2 border-dashed border-gray-300 relative">
                            @if($inbound->total_flight_duration)
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-white px-2 text-xs text-gray-400">{{ $inbound->total_flight_duration }}</span>
                            @endif
                        </div>
                        @if(is_array($inbound->connection) && count($inbound->connection) > 0)
                            <p class="text-center text-xs text-amber-600 mt-1">{{ count($inbound->connection) }} conexão(ões)</p>
                        @endif
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-gray-800">{{ $inbound->arrival_location }}</p>
                        <p class="text-sm text-gray-500">{{ $inbound->arrival_time }}</p>
                        @if($inbound->arrival_label)
                            <p class="text-xs text-gray-400">{{ $inbound->arrival_label }}</p>
                        @endif
                    </div>
                </div>
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

    <p class="mt-6 text-center text-sm text-gray-400">
        Link válido por {{ $order->expires_at->diffForHumans() }}.
    </p>
@endsection
