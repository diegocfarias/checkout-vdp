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
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3.5 rounded-xl transition-colors text-base">
                    Consultar pedido
                </button>
            </form>
        </div>
    </div>

    <script>
        function validateCpfDigits(cpf) {
            cpf = cpf.replace(/\D/g, '');
            if (cpf.length !== 11) return false;
            if (/^(\d)\1{10}$/.test(cpf)) return false;
            var sum = 0;
            for (var i = 0; i < 9; i++) sum += parseInt(cpf.charAt(i)) * (10 - i);
            var d1 = 11 - (sum % 11);
            if (d1 >= 10) d1 = 0;
            if (parseInt(cpf.charAt(9)) !== d1) return false;
            sum = 0;
            for (var i = 0; i < 10; i++) sum += parseInt(cpf.charAt(i)) * (11 - i);
            var d2 = 11 - (sum % 11);
            if (d2 >= 10) d2 = 0;
            return parseInt(cpf.charAt(10)) === d2;
        }

        var docInput = document.getElementById('document');
        var docError = document.createElement('p');
        docError.className = 'text-xs text-red-500 mt-1 hidden';
        docError.textContent = 'CPF inválido.';
        docInput.parentNode.appendChild(docError);

        docInput.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 11);
            if (v.length > 9) {
                v = v.slice(0, 3) + '.' + v.slice(3, 6) + '.' + v.slice(6, 9) + '-' + v.slice(9);
            } else if (v.length > 6) {
                v = v.slice(0, 3) + '.' + v.slice(3, 6) + '.' + v.slice(6);
            } else if (v.length > 3) {
                v = v.slice(0, 3) + '.' + v.slice(3);
            }
            this.value = v;
            docError.classList.add('hidden');
            docInput.classList.remove('border-red-500');
        });

        docInput.addEventListener('blur', function() {
            var raw = this.value.replace(/\D/g, '');
            if (raw.length === 11 && !validateCpfDigits(raw)) {
                docError.classList.remove('hidden');
                docInput.classList.add('border-red-500');
            }
        });

        document.getElementById('tracking_code').addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            var raw = docInput.value.replace(/\D/g, '');
            if (raw.length !== 11 || !validateCpfDigits(raw)) {
                e.preventDefault();
                docError.classList.remove('hidden');
                docInput.classList.add('border-red-500');
                docInput.focus();
            }
        });
    </script>
@endsection
