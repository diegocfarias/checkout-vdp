@extends('layouts.public')

@section('title', 'Dados dos Passageiros')

@section('content')
    {{-- Flight details --}}
    <div class="mb-8 space-y-4">
        <h2 class="text-2xl font-bold text-gray-800">Detalhes do Voo</h2>

        @if($outbound)
            <div class="bg-white rounded-lg shadow p-5 border border-gray-200">
                <div class="flex items-center gap-2 mb-3">
                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">IDA</span>
                    <span class="text-sm text-gray-500 uppercase">{{ $outbound->cia }}</span>
                    @if($outbound->flight_number)
                        <span class="text-sm text-gray-500">{{ $outbound->flight_number }}</span>
                    @endif
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-center">
                        <p class="text-lg font-bold text-gray-800">{{ $outbound->departure_location }}</p>
                        <p class="text-sm text-gray-500">{{ $outbound->departure_time }}</p>
                        @if($outbound->departure_label)
                            <p class="text-xs text-gray-400">{{ $outbound->departure_label }}</p>
                        @endif
                    </div>
                    <div class="flex-1 mx-4">
                        <div class="border-t-2 border-dashed border-gray-300 relative">
                            @if($outbound->total_flight_duration)
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-white px-2 text-xs text-gray-400">{{ $outbound->total_flight_duration }}</span>
                            @endif
                        </div>
                        @if(is_array($outbound->connection) && count($outbound->connection) > 0)
                            <p class="text-center text-xs text-orange-500 mt-1">{{ count($outbound->connection) }} conexão(ões)</p>
                        @endif
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-gray-800">{{ $outbound->arrival_location }}</p>
                        <p class="text-sm text-gray-500">{{ $outbound->arrival_time }}</p>
                        @if($outbound->arrival_label)
                            <p class="text-xs text-gray-400">{{ $outbound->arrival_label }}</p>
                        @endif
                    </div>
                </div>
                <div class="mt-3 flex gap-4 text-sm text-gray-500">
                    @if($outbound->miles_price)
                        <span>{{ number_format((int) $outbound->miles_price, 0, ',', '.') }} milhas</span>
                    @endif
                    @if($outbound->money_price)
                        <span>R$ {{ $outbound->money_price }}</span>
                    @endif
                    @if($outbound->tax)
                        <span>Taxa: R$ {{ $outbound->tax }}</span>
                    @endif
                </div>
            </div>
        @endif

        @if($inbound)
            <div class="bg-white rounded-lg shadow p-5 border border-gray-200">
                <div class="flex items-center gap-2 mb-3">
                    <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded">VOLTA</span>
                    <span class="text-sm text-gray-500 uppercase">{{ $inbound->cia }}</span>
                    @if($inbound->flight_number)
                        <span class="text-sm text-gray-500">{{ $inbound->flight_number }}</span>
                    @endif
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-center">
                        <p class="text-lg font-bold text-gray-800">{{ $inbound->departure_location }}</p>
                        <p class="text-sm text-gray-500">{{ $inbound->departure_time }}</p>
                        @if($inbound->departure_label)
                            <p class="text-xs text-gray-400">{{ $inbound->departure_label }}</p>
                        @endif
                    </div>
                    <div class="flex-1 mx-4">
                        <div class="border-t-2 border-dashed border-gray-300 relative">
                            @if($inbound->total_flight_duration)
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-white px-2 text-xs text-gray-400">{{ $inbound->total_flight_duration }}</span>
                            @endif
                        </div>
                        @if(is_array($inbound->connection) && count($inbound->connection) > 0)
                            <p class="text-center text-xs text-orange-500 mt-1">{{ count($inbound->connection) }} conexão(ões)</p>
                        @endif
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-gray-800">{{ $inbound->arrival_location }}</p>
                        <p class="text-sm text-gray-500">{{ $inbound->arrival_time }}</p>
                        @if($inbound->arrival_label)
                            <p class="text-xs text-gray-400">{{ $inbound->arrival_label }}</p>
                        @endif
                    </div>
                </div>
                <div class="mt-3 flex gap-4 text-sm text-gray-500">
                    @if($inbound->miles_price)
                        <span>{{ number_format((int) $inbound->miles_price, 0, ',', '.') }} milhas</span>
                    @endif
                    @if($inbound->money_price)
                        <span>R$ {{ $inbound->money_price }}</span>
                    @endif
                    @if($inbound->tax)
                        <span>Taxa: R$ {{ $inbound->tax }}</span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Passenger form --}}
    <div class="bg-white rounded-lg shadow border border-gray-200 p-4 sm:p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-1">Dados dos Passageiros</h2>
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

        <form action="{{ route('checkout.store', $order->token) }}" method="POST">
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

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 mt-4">
                Confirmar Passageiros
            </button>
        </form>
    </div>

    <div class="mt-4 text-center text-sm text-gray-400">
        Este link expira em {{ $order->expires_at->diffForHumans() }}.
    </div>

    <style>
        details[open] .details-open-rotate { transform: rotate(180deg); }
        .input-error {
            border-color: #ef4444 !important;
        }
        .input-error:focus {
            border-color: #ef4444 !important;
            --tw-ring-color: #ef4444 !important;
        }
        .error-msg {
            display: none;
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        .error-msg.visible {
            display: block;
        }
    </style>

    <script>
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
            const span = input.nextElementSibling;
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

        document.querySelector('form').addEventListener('submit', function (e) {
            const inputs = this.querySelectorAll('.v-input');
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
                firstInvalid.focus();
            }
        });
    </script>
@endsection
