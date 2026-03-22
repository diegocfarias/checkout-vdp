@extends('layouts.public')

@section('title', 'Acompanhar Pedido')

@section('content')
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
            <div class="text-center mb-6">
                <div class="mx-auto w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">Acompanhar pedido</h1>
                <p class="text-sm text-gray-500 mt-1">Informe o código do pedido e seu CPF para consultar.</p>
            </div>

            @if($errors->any())
                <div class="mb-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('tracking.search') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="tracking_code" class="block text-sm font-medium text-gray-700 mb-1">Código do pedido</label>
                    <input type="text" name="tracking_code" id="tracking_code"
                           value="{{ old('tracking_code') }}"
                           placeholder="VDP-XXXX"
                           maxlength="10"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-4 py-3 border uppercase"
                           required>
                </div>

                <div>
                    <label for="document" class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                    <input type="text" name="document" id="document"
                           value="{{ old('document') }}"
                           placeholder="000.000.000-00"
                           inputmode="numeric"
                           maxlength="14"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-4 py-3 border"
                           required>
                </div>

                <button type="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3.5 rounded-xl transition-colors text-base">
                    Consultar pedido
                </button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('document').addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 11);
            if (v.length > 9) {
                v = v.slice(0, 3) + '.' + v.slice(3, 6) + '.' + v.slice(6, 9) + '-' + v.slice(9);
            } else if (v.length > 6) {
                v = v.slice(0, 3) + '.' + v.slice(3, 6) + '.' + v.slice(6);
            } else if (v.length > 3) {
                v = v.slice(0, 3) + '.' + v.slice(3);
            }
            this.value = v;
        });

        document.getElementById('tracking_code').addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });
    </script>
@endsection
