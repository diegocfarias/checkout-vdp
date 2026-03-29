@extends('layouts.public')

@section('title', 'Minha Conta')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center gap-4 mb-8">
            @if($customer->avatar_url)
                <img src="{{ $customer->avatar_url }}" alt="" class="w-14 h-14 rounded-full">
            @else
                <span class="w-14 h-14 rounded-full bg-blue-600 flex items-center justify-center text-white text-xl font-bold shrink-0">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
            @endif
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Olá, {{ explode(' ', $customer->name)[0] }}!</h1>
                <p class="text-sm text-gray-500">Bem-vindo à sua conta.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-{{ $customer->isAffiliate() ? '4' : '3' }} gap-4 mb-8">
            <a href="{{ route('customer.orders') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-blue-300 transition-colors group">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 group-hover:text-blue-700">Meus pedidos</h3>
                </div>
                <p class="text-sm text-gray-500">Acompanhe seus pedidos e voos.</p>
            </a>

            <a href="{{ route('customer.passengers') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-blue-300 transition-colors group">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 group-hover:text-blue-700">Meus passageiros</h3>
                </div>
                <p class="text-sm text-gray-500">Passageiros salvos para compras rápidas.</p>
            </a>

            <a href="{{ route('customer.profile') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-blue-300 transition-colors group">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 group-hover:text-blue-700">Meu perfil</h3>
                </div>
                <p class="text-sm text-gray-500">Gerencie seus dados pessoais.</p>
            </a>

            @if($customer->isAffiliate())
            <a href="{{ route('customer.referrals') }}" class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5 hover:border-emerald-400 transition-colors group">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 group-hover:text-emerald-700">Indicações</h3>
                </div>
                @php $balance = app(\App\Services\ReferralService::class)->getAvailableBalance($customer); @endphp
                <p class="text-sm text-emerald-600 font-semibold">Saldo: R$ {{ number_format($balance, 2, ',', '.') }}</p>
            </a>
            @endif
        </div>

        @if($recentOrders->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Pedidos recentes</h3>
                <div class="space-y-3">
                    @foreach($recentOrders as $order)
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
                        @endphp
                        <a href="{{ route('customer.order.show', $order) }}" class="block p-3 rounded-lg hover:bg-gray-50 transition-colors border border-gray-100">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-mono font-semibold text-gray-500 bg-gray-100 px-2 py-1 rounded">{{ $order->tracking_code }}</span>
                                    <span class="text-sm text-gray-700">
                                        {{ strtoupper($order->departure_iata) }} → {{ strtoupper($order->arrival_iata) }}
                                    </span>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $statusLabels[$order->status] ?? $order->status }}
                                </span>
                            </div>
                            @if($order->flightSearch && $order->flightSearch->outbound_date)
                                <p class="text-xs text-gray-500 pl-2">{{ $order->flightSearch->outbound_date->format('d/m/Y') }}@if($order->flightSearch->inbound_date) — {{ $order->flightSearch->inbound_date->format('d/m/Y') }}@endif</p>
                            @endif
                        </a>
                    @endforeach
                </div>

                @if($recentOrders->count() >= 5)
                    <div class="mt-4 text-center">
                        <a href="{{ route('customer.orders') }}" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Ver todos os pedidos</a>
                    </div>
                @endif
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <p class="text-gray-500 mb-3">Você ainda não tem pedidos.</p>
                <a href="{{ route('search.home') }}" class="text-blue-600 hover:text-blue-700 font-medium text-sm">Buscar passagens</a>
            </div>
        @endif
    </div>
@endsection
