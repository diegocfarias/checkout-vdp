@extends('layouts.public')

@section('title', 'Dados dos Passageiros')

@section('content')
    @php
        $orderTotal = 0;
        foreach ($order->flights ?? [] as $flight) {
            $orderTotal += (float) ($flight->money_price ?? 0);
            $orderTotal += (float) ($flight->tax ?? 0);
        }
    @endphp

    <div class="pb-32">
        <a href="{{ route('checkout.show', $order->token) }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Voltar à viagem
        </a>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-1">Dados dos passageiros</h2>
            <p class="text-sm text-gray-500 mb-6">Preencha os dados de {{ $order->passengers_count }} passageiro(s).</p>

            @if($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('checkout.store', $order->token) }}" method="POST" id="checkout-form">
                @csrf

                @for($i = 0; $i < $order->passengers_count; $i++)
                    <details class="passenger-accordion mb-4 border border-gray-200 rounded-lg overflow-hidden" {{ $i === 0 ? 'open' : '' }}>
                        <summary class="cursor-pointer select-none bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-100 flex items-center justify-between">
                            <span>Passageiro {{ $i + 1 }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform details-open-rotate" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </summary>

                        <div class="p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="passengers_{{ $i }}_full_name" class="block text-sm font-medium text-gray-700 mb-1">Nome completo</label>
                                    <input type="text" name="passengers[{{ $i }}][full_name]" id="passengers_{{ $i }}_full_name"
                                           value="{{ old("passengers.{$i}.full_name") }}"
                                           data-validate="name"
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2 border"
                                           required>
                                    <span class="error-msg"></span>
                                </div>

                                <div>
                                    @if($order->isMercosul())
                                        <label for="passengers_{{ $i }}_document" class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                        <input type="text" name="passengers[{{ $i }}][document]" id="passengers_{{ $i }}_document"
                                               value="{{ old("passengers.{$i}.document") }}"
                                               placeholder="000.000.000-00"
                                               inputmode="numeric"
                                               maxlength="14"
                                               data-mask="cpf"
                                               data-validate="cpf"
                                               class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2 border"
                                               required>
                                        <span class="error-msg"></span>
                                    @else
                                        <label for="passengers_{{ $i }}_document" class="block text-sm font-medium text-gray-700 mb-1">Documento (CPF/Passaporte)</label>
                                        <input type="text" name="passengers[{{ $i }}][document]" id="passengers_{{ $i }}_document"
                                               value="{{ old("passengers.{$i}.document") }}"
                                               data-validate="required"
                                               class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2 border"
                                               required>
                                        <span class="error-msg"></span>
                                    @endif
                                </div>

                                <div>
                                    <label for="passengers_{{ $i }}_birth_date" class="block text-sm font-medium text-gray-700 mb-1">Data de nascimento</label>
                                    <input type="text" name="passengers[{{ $i }}][birth_date]" id="passengers_{{ $i }}_birth_date"
                                           value="{{ old("passengers.{$i}.birth_date") }}"
                                           placeholder="dd/mm/aaaa"
                                           inputmode="numeric"
                                           maxlength="10"
                                           data-mask="date"
                                           data-validate="date"
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2 border"
                                           required>
                                    <span class="error-msg"></span>
                                </div>

                                <div>
                                    <label for="passengers_{{ $i }}_email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                    <input type="email" name="passengers[{{ $i }}][email]" id="passengers_{{ $i }}_email"
                                           value="{{ old("passengers.{$i}.email") }}"
                                           data-validate="email"
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2 border"
                                           required>
                                    <span class="error-msg"></span>
                                </div>

                                <div>
                                    <label for="passengers_{{ $i }}_phone" class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                                    <input type="tel" name="passengers[{{ $i }}][phone]" id="passengers_{{ $i }}_phone"
                                           value="{{ old("passengers.{$i}.phone") }}"
                                           data-validate="phone"
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2 border"
                                           required>
                                    <span class="error-msg"></span>
                                </div>
                            </div>
                        </div>
                    </details>
                @endfor

                {{-- Forma de pagamento --}}
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Forma de pagamento</h3>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50 transition">
                            <input type="radio" name="payment_method" value="pix" {{ old('payment_method', 'pix') === 'pix' ? 'checked' : '' }} class="payment-method-radio">
                            <span class="font-medium">PIX</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50 transition">
                            <input type="radio" name="payment_method" value="credit_card" {{ old('payment_method') === 'credit_card' ? 'checked' : '' }} class="payment-method-radio">
                            <span class="font-medium">Cartão de crédito</span>
                        </label>
                    </div>

                    {{-- Campos do cartão --}}
                    <div id="card-fields" class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200 hidden">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Dados do cartão</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label for="card_number" class="block text-sm font-medium text-gray-700 mb-1">Número do cartão</label>
                                <input type="text" name="card_number" id="card_number" maxlength="19" placeholder="0000 0000 0000 0000"
                                       inputmode="numeric" data-mask="card" data-validate="card"
                                       class="card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2 border"
                                       value="{{ old('card_number') }}">
                                <span class="error-msg"></span>
                            </div>
                            <div>
                                <label for="card_name" class="block text-sm font-medium text-gray-700 mb-1">Nome no cartão</label>
                                <input type="text" name="card_name" id="card_name" placeholder="Como está no cartão"
                                       class="card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2 border"
                                       value="{{ old('card_name') }}">
                                <span class="error-msg"></span>
                            </div>
                            <div class="md:col-span-2 card-validity-row flex flex-wrap gap-4">
                                <div class="card-validity-group">
                                    <label for="card_month" class="block text-sm font-medium text-gray-700 mb-1">Validade</label>
                                    <div class="flex items-center gap-1">
                                        <input type="text" name="card_month" id="card_month" maxlength="2" placeholder="MM"
                                               inputmode="numeric" data-mask="card-month" data-validate="card-month"
                                               class="card-input v-input w-14 text-center rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-2 py-2 border"
                                               value="{{ old('card_month') }}">
                                        <span class="text-gray-500 font-medium">/</span>
                                        <input type="text" name="card_year" id="card_year" maxlength="2" placeholder="AA"
                                               inputmode="numeric" data-mask="card-year" data-validate="card-year"
                                               class="card-input v-input w-14 text-center rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-2 py-2 border"
                                               value="{{ old('card_year') }}">
                                    </div>
                                    <span class="error-msg card-validity-error"></span>
                                </div>
                                <div>
                                    <label for="card_cvv" class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                                    <input type="text" name="card_cvv" id="card_cvv" maxlength="4" placeholder="123"
                                           inputmode="numeric" data-mask="cvv" data-validate="cvv"
                                           class="card-input v-input w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-2 py-2 border"
                                           value="{{ old('card_cvv') }}">
                                    <span class="error-msg"></span>
                                </div>
                            </div>
                            <div>
                                <label for="installments" class="block text-sm font-medium text-gray-700 mb-1">Parcelas</label>
                                <select name="installments" id="installments" class="card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-2 border">
                                    @for($i = 1; $i <= 12; $i++)
                                        <option value="{{ $i }}" {{ old('installments', 1) == $i ? 'selected' : '' }}>{{ $i }}x</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Rodapé fixo --}}
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg z-10">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <details class="group mb-3">
                <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-800 flex items-center gap-1">
                    <svg class="w-4 h-4 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                    Ver detalhes da compra
                </summary>
                <div class="mt-2 pt-2 border-t border-gray-100 text-sm text-gray-600 space-y-1">
                    @foreach($order->flights ?? [] as $flight)
                        @php
                            $flightTotal = (float) ($flight->money_price ?? 0) + (float) ($flight->tax ?? 0);
                        @endphp
                        <div class="flex justify-between">
                            <span>{{ $flight->direction === 'outbound' ? 'Ida' : 'Volta' }} {{ $flight->departure_location }} → {{ $flight->arrival_location }}</span>
                            <span>R$ {{ number_format($flightTotal, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            </details>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Total</p>
                    <p class="text-xl font-bold text-gray-900">R$ {{ number_format($orderTotal, 2, ',', '.') }}</p>
                </div>
                <button type="submit" form="checkout-form"
                        class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-8 rounded-lg transition-colors">
                    Finalizar compra
                </button>
            </div>
        </div>
    </div>

    <style>
        details[open] .details-open-rotate { transform: rotate(180deg); }
        .input-error { border-color: #ef4444 !important; }
        .input-error:focus { border-color: #ef4444 !important; --tw-ring-color: #ef4444 !important; }
        .error-msg { display: none; color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem; }
        .error-msg.visible { display: block; }
    </style>

    <script>
        const cardFields = document.getElementById('card-fields');
        const paymentRadios = document.querySelectorAll('.payment-method-radio');
        const cardInputs = document.querySelectorAll('.card-input');

        function toggleCardFields() {
            const selected = document.querySelector('input[name="payment_method"]:checked');
            if (selected && selected.value === 'credit_card') {
                cardFields.classList.remove('hidden');
                cardInputs.forEach(i => { i.required = true; });
            } else {
                cardFields.classList.add('hidden');
                cardInputs.forEach(i => { i.required = false; });
            }
        }

        paymentRadios.forEach(r => r.addEventListener('change', toggleCardFields));
        toggleCardFields();

        document.querySelectorAll('[data-mask="card"]').forEach(input => {
            input.addEventListener('input', function () {
                let v = this.value.replace(/\D/g, '').slice(0, 16);
                this.value = v.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
            });
        });

        document.querySelectorAll('[data-mask="cvv"]').forEach(input => {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 4);
            });
        });

        document.querySelectorAll('[data-mask="card-month"]').forEach(input => {
            input.addEventListener('input', function () {
                let v = this.value.replace(/\D/g, '').slice(0, 2);
                if (v.length === 2 && parseInt(v) > 12) v = '12';
                if (v.length === 2 && parseInt(v) < 1) v = '01';
                this.value = v;
            });
        });

        document.querySelectorAll('[data-mask="card-year"]').forEach(input => {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 2);
            });
        });

        document.querySelectorAll('.passenger-accordion').forEach(el => {
            el.addEventListener('toggle', function () {
                if (this.open) {
                    document.querySelectorAll('.passenger-accordion').forEach(other => {
                        if (other !== el) other.removeAttribute('open');
                    });
                }
            });
        });

        document.querySelectorAll('[data-mask="date"]').forEach(input => {
            input.addEventListener('input', function () {
                let v = this.value.replace(/\D/g, '').slice(0, 8);
                if (v.length >= 5) {
                    v = v.slice(0, 2) + '/' + v.slice(2, 4) + '/' + v.slice(4);
                } else if (v.length >= 3) {
                    v = v.slice(0, 2) + '/' + v.slice(2);
                }
                this.value = v;
            });
        });

        document.querySelectorAll('[data-mask="cpf"]').forEach(input => {
            input.addEventListener('input', function () {
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
        });

        function validateCpfDigits(cpf) {
            cpf = cpf.replace(/\D/g, '');
            if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
            for (let t = 9; t < 11; t++) {
                let sum = 0;
                for (let i = 0; i < t; i++) {
                    sum += parseInt(cpf[i]) * ((t + 1) - i);
                }
                let digit = ((10 * sum) % 11) % 10;
                if (parseInt(cpf[t]) !== digit) return false;
            }
            return true;
        }

        function validateField(input) {
            const type = input.dataset.validate;
            const val = input.value.trim();
            let span = input.nextElementSibling;
            if (type === 'card-month' || type === 'card-year') {
                const row = input.closest('.card-validity-row');
                span = row ? row.querySelector('.card-validity-error') : span;
            }
            if (!span || !span.classList.contains('error-msg')) {
                span = input.parentElement?.querySelector('.error-msg') || span;
            }
            let error = '';

            if (!val) {
                error = 'Campo obrigatório.';
            } else {
                switch (type) {
                    case 'name':
                        if (val.length < 3) error = 'Informe o nome completo (mín. 3 caracteres).';
                        break;
                    case 'cpf':
                        if (!/^\d{3}\.\d{3}\.\d{3}-\d{2}$/.test(val)) {
                            error = 'CPF incompleto. Use o formato 000.000.000-00.';
                        } else if (!validateCpfDigits(val)) {
                            error = 'CPF inválido.';
                        }
                        break;
                    case 'date':
                        if (!/^\d{2}\/\d{2}\/\d{4}$/.test(val)) {
                            error = 'Data incompleta. Use o formato dd/mm/aaaa.';
                        } else {
                            const parts = val.split('/');
                            const day = parseInt(parts[0]), month = parseInt(parts[1]), year = parseInt(parts[2]);
                            const now = new Date().getFullYear();
                            if (month < 1 || month > 12) error = 'Mês inválido.';
                            else if (day < 1 || day > 31) error = 'Dia inválido.';
                            else if (year < 1900 || year > now) error = 'Ano inválido.';
                        }
                        break;
                    case 'email':
                        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) error = 'E-mail inválido.';
                        break;
                    case 'phone':
                        if (val.replace(/\D/g, '').length < 8) error = 'Telefone inválido (mín. 8 dígitos).';
                        break;
                    case 'card':
                        if (val.replace(/\D/g, '').length < 13) error = 'Número do cartão inválido.';
                        break;
                    case 'cvv':
                        if (val.length < 2 || val.length > 4) error = 'CVV inválido (2 a 4 dígitos).';
                        break;
                    case 'card-month':
                        if (val.length !== 2) error = 'Mês inválido (MM).';
                        else if (parseInt(val) < 1 || parseInt(val) > 12) error = 'Mês deve ser entre 01 e 12.';
                        break;
                    case 'card-year':
                        if (val.length !== 2) error = 'Ano inválido (AA).';
                        else {
                            const y = parseInt(val);
                            const currentYear = new Date().getFullYear() % 100;
                            if (y < currentYear || y > currentYear + 15) error = 'Ano inválido.';
                        }
                        break;
                    case 'required':
                        break;
                }
            }

            if (error) {
                input.classList.add('input-error');
                if (span && span.classList.contains('error-msg')) {
                    span.textContent = error;
                    span.classList.add('visible');
                }
            } else {
                input.classList.remove('input-error');
                if (span && span.classList.contains('error-msg')) {
                    span.textContent = '';
                    span.classList.remove('visible');
                }
            }

            return !error;
        }

        document.querySelectorAll('.v-input').forEach(input => {
            input.addEventListener('blur', function () {
                validateField(this);
            });
        });

        document.getElementById('checkout-form').addEventListener('submit', function (e) {
            const isCreditCard = document.querySelector('input[name="payment_method"]:checked')?.value === 'credit_card';
            const inputs = this.querySelectorAll(isCreditCard ? '.v-input' : '.v-input:not(.card-input)');
            let firstInvalid = null;

            inputs.forEach(input => {
                if (!validateField(input) && !firstInvalid) {
                    firstInvalid = input;
                }
            });

            if (firstInvalid) {
                e.preventDefault();
                const accordion = firstInvalid.closest('.passenger-accordion');
                if (accordion && !accordion.open) {
                    document.querySelectorAll('.passenger-accordion').forEach(el => el.removeAttribute('open'));
                    accordion.setAttribute('open', '');
                }
                const cardFieldsEl = firstInvalid.closest('#card-fields');
                if (cardFieldsEl) cardFieldsEl.classList.remove('hidden');
                firstInvalid.focus();
            }
        });
    </script>
@endsection
