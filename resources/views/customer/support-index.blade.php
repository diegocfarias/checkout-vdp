@extends('layouts.public')

@section('title', 'Meus Atendimentos')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Meus atendimentos</h1>
                <p class="text-sm text-gray-500">Acompanhe suas solicitações de suporte.</p>
            </div>
            <a href="{{ route('customer.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Voltar
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if($tickets->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </div>
                <p class="text-gray-500 text-sm">Você ainda não abriu nenhuma solicitação.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($tickets as $ticket)
                    @php
                        $statusColors = [
                            'open' => 'bg-red-100 text-red-700',
                            'in_progress' => 'bg-amber-100 text-amber-700',
                            'awaiting_customer' => 'bg-blue-100 text-blue-700',
                            'awaiting_internal' => 'bg-gray-100 text-gray-600',
                            'resolved' => 'bg-emerald-100 text-emerald-700',
                            'closed' => 'bg-gray-100 text-gray-500',
                        ];
                    @endphp
                    <a href="{{ route('customer.support.show', $ticket) }}" class="block bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-blue-300 hover:shadow-md transition-all">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $statusColors[$ticket->status] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        #{{ $ticket->id }}
                                    </span>
                                </div>
                                <p class="font-medium text-gray-800 text-sm">
                                    {{ \App\Models\SupportTicket::SUBJECTS[$ticket->subject] ?? $ticket->subject }}
                                </p>
                                @if($ticket->order)
                                    <p class="text-xs text-gray-400 mt-0.5">Pedido {{ $ticket->order->tracking_code }}</p>
                                @endif
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs text-gray-400">{{ $ticket->created_at->format('d/m/Y') }}</p>
                                <p class="text-xs text-gray-400">{{ $ticket->created_at->format('H:i') }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
@endsection
