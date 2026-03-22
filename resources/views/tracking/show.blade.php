@extends('layouts.public')

@section('title', 'Pedido ' . $order->tracking_code)

@section('content')
    @php
        $outbound = $order->flights->firstWhere('direction', 'outbound');
        $inbound = $order->flights->firstWhere('direction', 'inbound');
        $histories = $order->statusHistories->sortByDesc('created_at');
        $currentStatus = $order->status;

        $statusConfig = [
            'pending' => ['label' => 'Pedido criado', 'color' => 'gray', 'bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400'],
            'awaiting_payment' => ['label' => 'Aguardando pagamento', 'color' => 'yellow', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'dot' => 'bg-yellow-500'],
            'awaiting_emission' => ['label' => 'Pagamento confirmado', 'color' => 'blue', 'bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'dot' => 'bg-blue-500'],
            'completed' => ['label' => 'Passagens emitidas', 'color' => 'green', 'bg' => 'bg-green-100', 'text' => 'text-green-800', 'dot' => 'bg-green-500'],
            'cancelled' => ['label' => 'Cancelado', 'color' => 'red', 'bg' => 'bg-red-100', 'text' => 'text-red-800', 'dot' => 'bg-red-500'],
        ];

        $current = $statusConfig[$currentStatus] ?? $statusConfig['pending'];
    @endphp

    <div class="max-w-lg mx-auto space-y-4">
        {{-- Status atual --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm text-gray-500">Pedido</p>
                    <p class="text-xl font-bold text-gray-900">{{ $order->tracking_code }}</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold {{ $current['bg'] }} {{ $current['text'] }}">
                    <span class="w-2 h-2 rounded-full {{ $current['dot'] }}"></span>
                    {{ $current['label'] }}
                </span>
            </div>

            @if($currentStatus === 'awaiting_emission')
                <div class="bg-blue-50 rounded-lg p-3">
                    <p class="text-sm text-blue-700">Seu pagamento foi confirmado. Estamos encaminhando para emissão das passagens.</p>
                </div>
            @elseif($currentStatus === 'completed')
                <div class="bg-green-50 rounded-lg p-3">
                    <p class="text-sm text-green-700">Suas passagens foram emitidas! Confira seu e-mail para mais detalhes.</p>
                </div>
            @elseif($currentStatus === 'cancelled')
                <div class="bg-red-50 rounded-lg p-3">
                    <p class="text-sm text-red-700">Este pedido foi cancelado. Entre em contato pelo WhatsApp se precisar de ajuda.</p>
                </div>
            @endif
        </div>

        {{-- Voos --}}
        @if($outbound || $inbound)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Sua viagem</h3>

                @if($outbound)
                    <div class="bg-gray-50 rounded-lg p-4 mb-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="bg-slate-200 text-slate-700 text-xs font-semibold px-2 py-0.5 rounded">IDA</span>
                            <span class="text-sm text-gray-500 uppercase">{{ $outbound->cia }}</span>
                            @if($outbound->flight_number)
                                <span class="text-sm text-gray-500">{{ $outbound->flight_number }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <p class="font-medium text-gray-800">{{ $outbound->departure_location }}</p>
                                <p class="text-gray-500">{{ $outbound->departure_time }}</p>
                                @if($outbound->departure_label)
                                    <p class="text-xs text-gray-400">{{ $outbound->departure_label }}</p>
                                @endif
                            </div>
                            <div class="flex-1 mx-3 text-center">
                                @if($outbound->total_flight_duration)
                                    <span class="text-gray-400 text-xs">{{ $outbound->total_flight_duration }}</span>
                                @endif
                                <div class="border-t border-gray-300 mt-1"></div>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-800">{{ $outbound->arrival_location }}</p>
                                <p class="text-gray-500">{{ $outbound->arrival_time }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if($inbound)
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="bg-slate-200 text-slate-700 text-xs font-semibold px-2 py-0.5 rounded">VOLTA</span>
                            <span class="text-sm text-gray-500 uppercase">{{ $inbound->cia }}</span>
                            @if($inbound->flight_number)
                                <span class="text-sm text-gray-500">{{ $inbound->flight_number }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <p class="font-medium text-gray-800">{{ $inbound->departure_location }}</p>
                                <p class="text-gray-500">{{ $inbound->departure_time }}</p>
                                @if($inbound->departure_label)
                                    <p class="text-xs text-gray-400">{{ $inbound->departure_label }}</p>
                                @endif
                            </div>
                            <div class="flex-1 mx-3 text-center">
                                @if($inbound->total_flight_duration)
                                    <span class="text-gray-400 text-xs">{{ $inbound->total_flight_duration }}</span>
                                @endif
                                <div class="border-t border-gray-300 mt-1"></div>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-800">{{ $inbound->arrival_location }}</p>
                                <p class="text-gray-500">{{ $inbound->arrival_time }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Timeline --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-5">Histórico</h3>

            <div class="relative">
                @foreach($histories as $history)
                    @php
                        $hConfig = $statusConfig[$history->status] ?? $statusConfig['pending'];
                        $isFirst = $loop->first;
                    @endphp
                    <div class="flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
                        <div class="flex flex-col items-center">
                            <div class="w-3.5 h-3.5 rounded-full {{ $isFirst ? $hConfig['dot'] : 'bg-gray-300' }} ring-4 {{ $isFirst ? 'ring-' . $hConfig['color'] . '-100' : 'ring-gray-100' }} shrink-0 mt-0.5"></div>
                            @if(!$loop->last)
                                <div class="w-0.5 flex-1 bg-gray-200 mt-1"></div>
                            @endif
                        </div>
                        <div class="pb-1">
                            <p class="text-sm font-semibold {{ $isFirst ? 'text-gray-900' : 'text-gray-500' }}">{{ $history->description }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $history->created_at->format('d/m/Y \à\s H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Ajuda --}}
        <div class="text-center py-2">
            <p class="text-xs text-gray-400">Dúvidas? Entre em contato pelo WhatsApp.</p>
        </div>
    </div>
@endsection
