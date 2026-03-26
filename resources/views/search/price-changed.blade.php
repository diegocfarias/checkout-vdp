@extends('layouts.public')

@section('title', 'Preço atualizado')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6 text-center border-b border-gray-100">
            @if($diff > 0)
                <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <h2 class="text-lg font-bold text-gray-800 mb-1">O preço aumentou</h2>
            @else
                <div class="w-14 h-14 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 class="text-lg font-bold text-gray-800 mb-1">O preço diminuiu!</h2>
            @endif
            <p class="text-sm text-gray-500">O valor do voo foi atualizado desde a sua busca.</p>
        </div>

        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500">Preço anterior</span>
                <span class="text-sm text-gray-500 line-through">R$ {{ number_format($oldTotal, 2, ',', '.') }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-gray-800">Novo preço</span>
                <span class="text-xl font-bold {{ $diff > 0 ? 'text-amber-600' : 'text-emerald-600' }}">R$ {{ number_format($newTotal, 2, ',', '.') }}</span>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                <span class="text-xs text-gray-400">Diferença</span>
                <span class="text-sm font-medium {{ $diff > 0 ? 'text-red-500' : 'text-emerald-600' }}">
                    {{ $diff > 0 ? '+' : '' }}R$ {{ number_format($diff, 2, ',', '.') }}
                </span>
            </div>
        </div>

        <div class="p-6 bg-gray-50 border-t border-gray-100 space-y-3">
            <form action="{{ route('search.select') }}" method="POST">
                @csrf
                <input type="hidden" name="search_id" value="{{ $searchId }}">
                <input type="hidden" name="outbound" value="{{ json_encode($outbound, JSON_UNESCAPED_UNICODE) }}">
                @if($inbound)
                    <input type="hidden" name="inbound" value="{{ json_encode($inbound, JSON_UNESCAPED_UNICODE) }}">
                @endif
                <input type="hidden" name="confirmed" value="1">
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg transition-colors">
                    Continuar com o novo preço
                </button>
            </form>
            <a href="{{ route('search.home') }}" class="block w-full text-center text-sm text-gray-500 hover:text-gray-700 font-medium py-2">
                Fazer nova busca
            </a>
        </div>
    </div>
</div>
@endsection
