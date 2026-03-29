@extends('layouts.public')

@section('title', 'Atendimento #' . $ticket->id)

@section('content')
    <div class="max-w-3xl mx-auto">
        <a href="{{ route('customer.support.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Voltar aos atendimentos
        </a>

        @if(session('success'))
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

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

        {{-- Informações do ticket --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $statusColors[$ticket->status] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}
                    </span>
                    <span class="text-xs text-gray-400">#{{ $ticket->id }}</span>
                </div>
                <span class="text-xs text-gray-400">{{ $ticket->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</span>
            </div>

            <h1 class="text-lg font-bold text-gray-800 mb-1">
                {{ \App\Models\SupportTicket::SUBJECTS[$ticket->subject] ?? $ticket->subject }}
            </h1>

            @if($ticket->order)
                <p class="text-sm text-gray-500 mb-3">
                    Referente ao pedido
                    <a href="{{ route('customer.order.show', $ticket->order) }}" class="text-blue-600 hover:underline font-medium">{{ $ticket->order->tracking_code }}</a>
                </p>
            @endif

            <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $ticket->message }}</div>
        </div>

        {{-- Thread de mensagens --}}
        @if($ticket->messages->isNotEmpty())
            <div class="space-y-3 mb-4">
                @foreach($ticket->messages as $msg)
                    @php
                        $isAgent = $msg->user_id !== null;
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border {{ $isAgent ? 'border-blue-200' : 'border-gray-200' }} p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold {{ $isAgent ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $isAgent ? 'A' : 'V' }}
                                </div>
                                <span class="text-sm font-medium text-gray-700">
                                    {{ $isAgent ? 'Equipe de Suporte' : 'Você' }}
                                </span>
                            </div>
                            <span class="text-xs text-gray-400">{{ $msg->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap pl-9">{{ $msg->message }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Formulário de resposta --}}
        @if($ticket->status !== 'closed')
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-800 mb-3">Responder</h3>
                <form method="POST" action="{{ route('customer.support.reply', $ticket) }}">
                    @csrf
                    <div class="mb-3">
                        <textarea name="message" rows="4" required maxlength="5000" placeholder="Escreva sua resposta..."
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none @error('message') border-red-300 @enderror">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-lg transition-colors text-sm">
                        Enviar resposta
                    </button>
                </form>
            </div>
        @else
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 text-center">
                <p class="text-sm text-gray-500">Este atendimento foi encerrado.</p>
            </div>
        @endif
    </div>
@endsection
