@extends('layouts.public')

@section('title', 'Pagamento Confirmado')

@section('content')
    <div class="max-w-lg mx-auto text-center">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-8">
            <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-2">Pagamento confirmado!</h2>
            <p class="text-gray-500 mb-6">Seu pedido está sendo encaminhado para emissão. Em breve você receberá a confirmação.</p>

            @php
                $outbound = $order->flights->firstWhere('direction', 'outbound');
                $inbound = $order->flights->firstWhere('direction', 'inbound');
            @endphp

            @if($outbound)
                <div class="text-left bg-gray-50 rounded-lg p-4 mb-3">
                    <p class="text-xs font-semibold text-blue-600 mb-1">IDA</p>
                    <p class="text-sm text-gray-700">
                        {{ $outbound->departure_location }} &rarr; {{ $outbound->arrival_location }}
                        @if($outbound->flight_number)
                            <span class="text-gray-400">({{ $outbound->flight_number }})</span>
                        @endif
                    </p>
                </div>
            @endif

            @if($inbound)
                <div class="text-left bg-gray-50 rounded-lg p-4 mb-3">
                    <p class="text-xs font-semibold text-green-600 mb-1">VOLTA</p>
                    <p class="text-sm text-gray-700">
                        {{ $inbound->departure_location }} &rarr; {{ $inbound->arrival_location }}
                        @if($inbound->flight_number)
                            <span class="text-gray-400">({{ $inbound->flight_number }})</span>
                        @endif
                    </p>
                </div>
            @endif

            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                @php $waNum = \App\Models\Setting::get('whatsapp_number'); @endphp
                <p class="text-sm text-blue-700">Acompanhe
                    @if($waNum)
                        pelo <a href="https://wa.me/{{ $waNum }}" target="_blank" class="underline font-semibold hover:text-blue-900">WhatsApp</a>
                    @else
                        pelo WhatsApp
                    @endif
                    as atualizações sobre a emissão das suas passagens.
                </p>
            </div>
        </div>
    </div>
@endsection
