@extends('layouts.public')

@section('title', 'Meus Passageiros')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Meus passageiros</h1>
                <p class="text-sm text-gray-500">Passageiros salvos para preenchimento rápido no checkout.</p>
            </div>
            <a href="{{ route('customer.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Voltar
            </a>
        </div>

        @if(session('status'))
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                {{ session('status') }}
            </div>
        @endif

        @if($savedPassengers->isNotEmpty())
            <div class="space-y-3">
                @php
                    $natLabels = [
                        'BR' => 'Brasil', 'AR' => 'Argentina', 'UY' => 'Uruguai', 'PY' => 'Paraguai',
                        'CL' => 'Chile', 'CO' => 'Colômbia', 'PE' => 'Peru', 'BO' => 'Bolívia',
                        'EC' => 'Equador', 'VE' => 'Venezuela', 'US' => 'Estados Unidos', 'PT' => 'Portugal',
                        'ES' => 'Espanha', 'IT' => 'Itália', 'DE' => 'Alemanha', 'FR' => 'França',
                        'GB' => 'Reino Unido', 'JP' => 'Japão', 'XX' => 'Outro',
                    ];
                @endphp
                @foreach($savedPassengers as $passenger)
                    @php
                        $doc = $passenger->document;
                        $docFormatted = $doc && strlen($doc) === 11
                            ? substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2)
                            : $doc;
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">{{ $passenger->full_name }}</h3>
                                    <div class="mt-1 space-y-0.5 text-sm text-gray-500">
                                        <p>{{ $natLabels[$passenger->nationality ?? 'BR'] ?? $passenger->nationality }}</p>
                                        @if($docFormatted)
                                            <p>CPF: {{ $docFormatted }}</p>
                                        @endif
                                        @if($passenger->passport_number)
                                            <p>Passaporte: {{ $passenger->passport_number }}</p>
                                        @endif
                                        @if($passenger->passport_expiry)
                                            <p>Validade: {{ $passenger->passport_expiry->format('d/m/Y') }}</p>
                                        @endif
                                        <p>{{ $passenger->email }}</p>
                                        <p>{{ $passenger->birth_date->format('d/m/Y') }}</p>
                                        @if($passenger->phone)
                                            <p>{{ $passenger->phone }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('customer.passenger.destroy', $passenger) }}"
                                  onsubmit="return confirm('Tem certeza que deseja remover este passageiro?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs bg-red-50 hover:bg-red-100 text-red-600 hover:text-red-700 font-medium px-3 py-2 rounded-lg transition-colors whitespace-nowrap">
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <p class="text-gray-500 mb-3">Você ainda não tem passageiros salvos.</p>
                <p class="text-sm text-gray-400">Ao preencher os dados no checkout, você poderá salvar passageiros para futuras compras.</p>
            </div>
        @endif
    </div>
@endsection
