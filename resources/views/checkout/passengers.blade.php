@extends('layouts.public')

@section('title', 'Dados dos Passageiros')

@push('head')
    <script src="https://scripts.appmax.com.br/appmax.min.js"></script>
@endpush

@section('content')
    @php
        $payingPax = $order->total_adults + $order->total_children;
        if ($payingPax < 1) $payingPax = 1;

        $subtotalPassagensPorPax = 0;
        $subtotalTaxasPorPax = 0;
        foreach ($order->flights ?? [] as $flight) {
            $subtotalPassagensPorPax += (float) ($flight->money_price ?? 0);
            $subtotalTaxasPorPax += (float) ($flight->tax ?? 0);
        }
        $subtotalPassagens = $subtotalPassagensPorPax * $payingPax;
        $subtotalTaxas = $subtotalTaxasPorPax * $payingPax;
        $orderTotal = $subtotalPassagens + $subtotalTaxas;
    @endphp

    <div class="pb-32">
        @include('partials._checkout_stepper', ['currentStep' => 2])
        <a href="{{ route('checkout.show', $order->token) }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Voltar à viagem
        </a>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sm:p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-1">Dados dos passageiros</h2>
            <p class="text-sm text-gray-500 mb-8">Preencha os dados de {{ $order->passengers_count }} passageiro(s).</p>

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
                <input type="hidden" name="client_ip" id="client_ip" value="">
                <input type="hidden" name="card_token" id="card_token" value="">

                @for($i = 0; $i < $order->passengers_count; $i++)
                    <details class="passenger-accordion mb-6 border border-gray-200 rounded-lg overflow-hidden" data-passenger-index="{{ $i }}" {{ $i === 0 ? 'open' : '' }}>
                        <summary class="cursor-pointer select-none bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-100 flex items-center justify-between">
                            <span>Passageiro {{ $i + 1 }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform details-open-rotate" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </summary>

                        <div class="p-5 sm:p-6">
                            @if(($savedPassengers ?? collect())->isNotEmpty())
                                <div class="mb-5 pb-5 border-b border-gray-100">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Usar passageiro salvo</label>
                                    <select class="saved-passenger-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border" data-index="{{ $i }}">
                                        <option value="">Preencher manualmente</option>
                                        @foreach($savedPassengers as $sp)
                                            @php
                                                $spDoc = $sp->document;
                                                $spDocMasked = $spDoc && strlen($spDoc) === 11
                                                    ? '***.' . substr($spDoc, 3, 3) . '.' . substr($spDoc, 6, 3) . '-' . substr($spDoc, 9, 2)
                                                    : ($spDoc ?: '');
                                                $spLabel = $sp->full_name;
                                                if ($sp->nationality !== 'BR' && $sp->passport_number) {
                                                    $spLabel .= ' (Pass. ***' . substr($sp->passport_number, -4) . ')';
                                                } elseif ($spDocMasked) {
                                                    $spLabel .= ' (' . $spDocMasked . ')';
                                                }
                                            @endphp
                                            <option value="{{ $sp->id }}"
                                                    data-name="{{ $sp->full_name }}"
                                                    data-nationality="{{ $sp->nationality ?? 'BR' }}"
                                                    data-document="{{ $spDoc && strlen($spDoc) === 11 ? substr($spDoc, 0, 3) . '.' . substr($spDoc, 3, 3) . '.' . substr($spDoc, 6, 3) . '-' . substr($spDoc, 9, 2) : ($spDoc ?? '') }}"
                                                    data-passport="{{ $sp->passport_number ?? '' }}"
                                                    data-passport-expiry="{{ $sp->passport_expiry ? $sp->passport_expiry->format('d/m/Y') : '' }}"
                                                    data-birth="{{ $sp->birth_date->format('d/m/Y') }}"
                                                    data-email="{{ $sp->email }}"
                                                    data-phone="{{ $sp->phone }}">
                                                {{ $spLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="passengers_{{ $i }}_full_name" class="block text-sm font-medium text-gray-700 mb-1">Nome completo</label>
                                    <input type="text" name="passengers[{{ $i }}][full_name]" id="passengers_{{ $i }}_full_name"
                                           value="{{ old("passengers.{$i}.full_name") }}"
                                           data-validate="name"
                                           maxlength="255"
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
                                           required>
                                    <span class="error-msg"></span>
                                </div>

                                <div>
                                    <label for="passengers_{{ $i }}_nationality" class="block text-sm font-medium text-gray-700 mb-1">Nacionalidade</label>
                                    <select name="passengers[{{ $i }}][nationality]" id="passengers_{{ $i }}_nationality"
                                            class="nationality-select v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
                                            data-index="{{ $i }}" required>
                                        <option value="BR" {{ old("passengers.{$i}.nationality", 'BR') === 'BR' ? 'selected' : '' }}>Brasil</option>
                                        <option value="AR" {{ old("passengers.{$i}.nationality") === 'AR' ? 'selected' : '' }}>Argentina</option>
                                        <option value="UY" {{ old("passengers.{$i}.nationality") === 'UY' ? 'selected' : '' }}>Uruguai</option>
                                        <option value="PY" {{ old("passengers.{$i}.nationality") === 'PY' ? 'selected' : '' }}>Paraguai</option>
                                        <option value="CL" {{ old("passengers.{$i}.nationality") === 'CL' ? 'selected' : '' }}>Chile</option>
                                        <option value="CO" {{ old("passengers.{$i}.nationality") === 'CO' ? 'selected' : '' }}>Colômbia</option>
                                        <option value="PE" {{ old("passengers.{$i}.nationality") === 'PE' ? 'selected' : '' }}>Peru</option>
                                        <option value="BO" {{ old("passengers.{$i}.nationality") === 'BO' ? 'selected' : '' }}>Bolívia</option>
                                        <option value="EC" {{ old("passengers.{$i}.nationality") === 'EC' ? 'selected' : '' }}>Equador</option>
                                        <option value="VE" {{ old("passengers.{$i}.nationality") === 'VE' ? 'selected' : '' }}>Venezuela</option>
                                        <option value="US" {{ old("passengers.{$i}.nationality") === 'US' ? 'selected' : '' }}>Estados Unidos</option>
                                        <option value="PT" {{ old("passengers.{$i}.nationality") === 'PT' ? 'selected' : '' }}>Portugal</option>
                                        <option value="ES" {{ old("passengers.{$i}.nationality") === 'ES' ? 'selected' : '' }}>Espanha</option>
                                        <option value="IT" {{ old("passengers.{$i}.nationality") === 'IT' ? 'selected' : '' }}>Itália</option>
                                        <option value="DE" {{ old("passengers.{$i}.nationality") === 'DE' ? 'selected' : '' }}>Alemanha</option>
                                        <option value="FR" {{ old("passengers.{$i}.nationality") === 'FR' ? 'selected' : '' }}>França</option>
                                        <option value="GB" {{ old("passengers.{$i}.nationality") === 'GB' ? 'selected' : '' }}>Reino Unido</option>
                                        <option value="JP" {{ old("passengers.{$i}.nationality") === 'JP' ? 'selected' : '' }}>Japão</option>
                                        <option value="XX" {{ old("passengers.{$i}.nationality") === 'XX' ? 'selected' : '' }}>Outro</option>
                                    </select>
                                    <span class="error-msg"></span>
                                </div>

                                <div id="passengers_{{ $i }}_cpf_wrapper">
                                    <label for="passengers_{{ $i }}_document" class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                    <input type="text" name="passengers[{{ $i }}][document]" id="passengers_{{ $i }}_document"
                                           value="{{ old("passengers.{$i}.document") }}"
                                           placeholder="000.000.000-00"
                                           inputmode="numeric"
                                           maxlength="14"
                                           data-mask="cpf"
                                           data-validate="cpf"
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
                                           required>
                                    <span class="error-msg"></span>
                                </div>

                                <div id="passengers_{{ $i }}_passport_wrapper" class="hidden">
                                    <label for="passengers_{{ $i }}_passport_number" class="block text-sm font-medium text-gray-700 mb-1">Passaporte</label>
                                    <input type="text" name="passengers[{{ $i }}][passport_number]" id="passengers_{{ $i }}_passport_number"
                                           value="{{ old("passengers.{$i}.passport_number") }}"
                                           placeholder="Nº do passaporte"
                                           maxlength="50"
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                                    <span class="error-msg"></span>
                                </div>

                                <div id="passengers_{{ $i }}_passport_expiry_wrapper" class="hidden">
                                    <label for="passengers_{{ $i }}_passport_expiry" class="block text-sm font-medium text-gray-700 mb-1">Validade do passaporte</label>
                                    <input type="text" name="passengers[{{ $i }}][passport_expiry]" id="passengers_{{ $i }}_passport_expiry"
                                           value="{{ old("passengers.{$i}.passport_expiry") }}"
                                           placeholder="dd/mm/aaaa"
                                           inputmode="numeric"
                                           maxlength="10"
                                           data-mask="date"
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                                    <span class="error-msg"></span>
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
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
                                           required>
                                    <span class="error-msg"></span>
                                </div>

                                <div>
                                    <label for="passengers_{{ $i }}_email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                    <input type="email" name="passengers[{{ $i }}][email]" id="passengers_{{ $i }}_email"
                                           value="{{ old("passengers.{$i}.email") }}"
                                           data-validate="email"
                                           maxlength="255"
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
                                           required>
                                    <span class="error-msg"></span>
                                </div>

                                <div>
                                    <label for="passengers_{{ $i }}_phone" class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                                    <input type="tel" name="passengers[{{ $i }}][phone]" id="passengers_{{ $i }}_phone"
                                           value="{{ old("passengers.{$i}.phone") }}"
                                           placeholder="(00) 00000-0000"
                                           inputmode="numeric"
                                           maxlength="15"
                                           data-mask="phone"
                                           data-validate="phone"
                                           class="v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
                                           required>
                                    <span class="error-msg"></span>
                                </div>
                            </div>

                            @if(auth('customer')->check())
                                <div class="mt-4 pt-3 border-t border-gray-100">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="passengers[{{ $i }}][save_passenger]" value="1"
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="text-sm text-gray-600">Salvar para futuras compras</span>
                                    </label>
                                </div>
                            @endif
                        </div>
                    </details>
                @endfor

                {{-- Dados do pagador --}}
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <div class="flex items-center gap-2 mb-1">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <h3 class="text-lg font-semibold text-gray-800">Dados do pagador</h3>
                    </div>
                    <p class="text-sm text-gray-500 mb-5">Informe os dados de quem está realizando o pagamento.</p>

                    @if($order->passengers_count > 0)
                    <div class="mb-4">
                        <label for="payer_copy_from" class="block text-sm font-medium text-gray-700 mb-1">Copiar dados do passageiro</label>
                        <select id="payer_copy_from" class="w-full sm:w-auto rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                            <option value="">Preencher manualmente</option>
                            @for($i = 0; $i < $order->passengers_count; $i++)
                                <option value="{{ $i }}">Passageiro {{ $i + 1 }}</option>
                            @endfor
                        </select>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="payer_name" class="block text-sm font-medium text-gray-700 mb-1">Nome completo</label>
                            <input type="text" name="payer_name" id="payer_name"
                                   value="{{ old('payer_name', auth('customer')->user()?->name) }}"
                                   data-validate="name"
                                   class="payer-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
                                   required>
                            <span class="error-msg"></span>
                        </div>
                        <div>
                            <label for="payer_email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                            <input type="email" name="payer_email" id="payer_email"
                                   value="{{ old('payer_email', auth('customer')->user()?->email) }}"
                                   data-validate="email"
                                   class="payer-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
                                   required>
                            <span class="error-msg"></span>
                        </div>
                        <div>
                            <label for="payer_document" class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                            @php
                                $customerCpf = auth('customer')->user()?->document;
                                $customerCpfFormatted = $customerCpf && strlen($customerCpf) === 11
                                    ? substr($customerCpf, 0, 3) . '.' . substr($customerCpf, 3, 3) . '.' . substr($customerCpf, 6, 3) . '-' . substr($customerCpf, 9, 2)
                                    : $customerCpf;
                            @endphp
                            <input type="text" name="payer_document" id="payer_document"
                                   value="{{ old('payer_document', $customerCpfFormatted) }}"
                                   placeholder="000.000.000-00"
                                   inputmode="numeric"
                                   maxlength="14"
                                   data-mask="cpf"
                                   data-validate="cpf"
                                   class="payer-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
                                   required>
                            <span class="error-msg"></span>
                        </div>
                    </div>
                </div>

                {{-- Créditos de indicação --}}
                @if($walletBalance > 0 && $isAffiliate)
                <div class="mt-8 pt-8 border-t border-gray-200" id="wallet-section">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            <h3 class="text-lg font-semibold text-gray-800">Usar créditos</h3>
                        </div>
                        <span class="text-sm text-emerald-600 font-semibold">Saldo: R$ {{ number_format($walletBalance, 2, ',', '.') }}</span>
                    </div>
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:border-blue-300 transition-colors">
                        <input type="checkbox" id="use_wallet_toggle" name="use_wallet" value="1"
                               class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <div class="flex-1">
                            <span class="text-sm font-medium text-gray-800">Usar meus créditos de indicação</span>
                            <p class="text-xs text-gray-500 mt-0.5">Será aplicado automaticamente até o valor total. Não acumulável com cupom.</p>
                        </div>
                    </label>
                </div>
                @endif

                {{-- Cupom / Código de indicação --}}
                <div class="mt-8 pt-8 border-t border-gray-200" id="coupon-section">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        <h3 class="text-lg font-semibold text-gray-800">Cupom ou código de indicação</h3>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" id="coupon_input" placeholder="Digite o código do cupom ou indicação"
                               class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border uppercase"
                               maxlength="20" autocomplete="off" value="{{ $refCookie }}">
                        <button type="button" id="btn-apply-coupon"
                                class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 transition-colors shrink-0">
                            Aplicar
                        </button>
                    </div>
                    <div id="coupon-feedback" class="mt-2 hidden">
                        <div id="coupon-success" class="hidden flex items-center justify-between p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm text-emerald-700" id="coupon-success-msg">
                                    Código <strong id="coupon-applied-code"></strong> aplicado: <strong id="coupon-applied-discount"></strong>
                                </span>
                            </div>
                            <button type="button" id="btn-remove-coupon" class="text-sm text-red-600 hover:text-red-800 font-medium">Remover</button>
                        </div>
                        <div id="coupon-error" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-sm text-red-700" id="coupon-error-msg"></p>
                        </div>
                    </div>
                    <input type="hidden" name="coupon_code" id="coupon_code" value="">
                </div>

                {{-- Forma de pagamento --}}
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <h3 class="text-lg font-semibold text-gray-800">Forma de pagamento</h3>
                    </div>
                    @php
                        $defaultMethod = ($pixEnabled ?? true) ? 'pix' : (($creditCardEnabled ?? true) ? 'credit_card' : 'pix');
                    @endphp
                    <div class="space-y-3">
                        @if($pixEnabled ?? true)
                            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition
                                {{ ($pixDiscount ?? 0) > 0
                                    ? 'border-blue-300 bg-blue-50/50 hover:bg-blue-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50'
                                    : 'border-gray-200 hover:bg-gray-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50' }}">
                                <input type="radio" name="payment_method" value="pix" {{ old('payment_method', $defaultMethod) === 'pix' ? 'checked' : '' }} class="payment-method-radio text-blue-600">
                                <div class="flex-1">
                                    <span class="font-medium">PIX</span>
                                    @if(($pixDiscount ?? 0) > 0)
                                        <p class="text-xs text-emerald-600 mt-0.5">Pagamento instantâneo com desconto</p>
                                    @endif
                                </div>
                                @if(($pixDiscount ?? 0) > 0)
                                    <span class="text-xs font-bold bg-emerald-600 text-white px-2.5 py-1 rounded-full">-{{ number_format($pixDiscount, 0) }}%</span>
                                @endif
                            </label>
                        @endif
                        @if($creditCardEnabled ?? true)
                            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50 transition">
                                <input type="radio" name="payment_method" value="credit_card" {{ old('payment_method', $defaultMethod) === 'credit_card' ? 'checked' : '' }} class="payment-method-radio">
                                <span class="font-medium">Cartão de crédito</span>
                            </label>
                        @endif
                    </div>

                    {{-- Campos do cartão --}}
                    <div id="card-fields" class="mt-5 p-5 bg-gray-50 rounded-lg border border-gray-200 hidden">
                        <h4 class="text-sm font-semibold text-gray-700 mb-4">Dados do cartão</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label for="card_number" class="block text-sm font-medium text-gray-700 mb-1">Número do cartão</label>
                                <input type="text" name="card_number" id="card_number" maxlength="19" placeholder="0000 0000 0000 0000"
                                       inputmode="numeric" data-mask="card" data-validate="card"
                                       class="card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
                                       value="{{ old('card_number') }}">
                                <span class="error-msg"></span>
                            </div>
                            <div>
                                <label for="card_name" class="block text-sm font-medium text-gray-700 mb-1">Nome no cartão</label>
                                <input type="text" name="card_name" id="card_name" placeholder="Como está no cartão"
                                       class="card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border"
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
                                <select name="installments" id="installments" class="card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                                    @for($i = 1; $i <= ($maxInstallments ?? 12); $i++)
                                        @php
                                            $rate = $interestRates[$i] ?? 0;
                                            $totalComJuros = $orderTotal * (1 + $rate / 100);
                                            $valorParcela = $totalComJuros / $i;
                                        @endphp
                                        <option value="{{ $i }}" data-total="{{ number_format($totalComJuros, 2, '.', '') }}" {{ old('installments', 1) == $i ? 'selected' : '' }}>
                                            {{ $i }}x de R$ {{ number_format($valorParcela, 2, ',', '.') }}{{ $rate > 0 ? ' com juros' : ' sem juros' }}
                                        </option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <div class="mt-5 pt-5 border-t border-gray-200">
                            <h4 class="text-sm font-semibold text-gray-700 mb-4">Endereço de cobrança</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="billing_zipcode" class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                    <div class="relative">
                                        <input type="text" name="billing_zipcode" id="billing_zipcode"
                                               value="{{ old('billing_zipcode') }}"
                                               placeholder="00000-000"
                                               inputmode="numeric"
                                               maxlength="9"
                                               data-mask="cep"
                                               data-validate="cep"
                                               class="billing-input card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                                        <span id="cep-loading" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                                            <svg class="w-4 h-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        </span>
                                    </div>
                                    <span class="error-msg"></span>
                                    <span id="cep-hint" class="hidden text-xs text-amber-600 mt-1"></span>
                                </div>
                                <div>
                                    <label for="billing_street" class="block text-sm font-medium text-gray-700 mb-1">Rua</label>
                                    <input type="text" name="billing_street" id="billing_street"
                                           value="{{ old('billing_street') }}"
                                           data-validate="required"
                                           class="billing-input card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                                    <span class="error-msg"></span>
                                </div>
                                <div>
                                    <label for="billing_number" class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                    <input type="text" name="billing_number" id="billing_number"
                                           value="{{ old('billing_number') }}"
                                           data-validate="required"
                                           class="billing-input card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                                    <span class="error-msg"></span>
                                </div>
                                <div>
                                    <label for="billing_complement" class="block text-sm font-medium text-gray-700 mb-1">Complemento <span class="text-gray-400 font-normal">(opcional)</span></label>
                                    <input type="text" name="billing_complement" id="billing_complement"
                                           value="{{ old('billing_complement') }}"
                                           class="billing-input card-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                                </div>
                                <div>
                                    <label for="billing_neighborhood" class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                    <input type="text" name="billing_neighborhood" id="billing_neighborhood"
                                           value="{{ old('billing_neighborhood') }}"
                                           data-validate="required"
                                           class="billing-input card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                                    <span class="error-msg"></span>
                                </div>
                                <div>
                                    <label for="billing_city" class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                    <input type="text" name="billing_city" id="billing_city"
                                           value="{{ old('billing_city') }}"
                                           data-validate="required"
                                           class="billing-input card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                                    <span class="error-msg"></span>
                                </div>
                                <div>
                                    <label for="billing_state" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                    <select name="billing_state" id="billing_state"
                                            data-validate="required"
                                            class="billing-input card-input v-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                                        <option value="">Selecione</option>
                                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                                            <option value="{{ $uf }}" {{ old('billing_state') === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                                        @endforeach
                                    </select>
                                    <span class="error-msg"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Rodapé fixo --}}
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.08)] z-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-sm text-gray-500">Total</span>
                        <span id="footer-total" class="text-2xl font-bold text-gray-900" data-base="{{ $orderTotal }}">R$ {{ number_format($orderTotal, 2, ',', '.') }}</span>
                    </div>
                    <p id="footer-discount-info" class="text-xs text-emerald-600 font-medium hidden"></p>
                </div>
                <button type="button" id="btn-detalhes-compra" class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Detalhes
                </button>
            </div>
            <button type="submit" form="checkout-form"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3.5 rounded-xl transition-colors text-base">
                Finalizar compra
            </button>
        </div>
    </div>

    {{-- Modal Detalhes da compra --}}
    <div id="modal-detalhes" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
        <div class="fixed inset-0 bg-black/50" id="modal-backdrop"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <h3 id="modal-titulo" class="text-lg font-semibold text-gray-800">Detalhes da compra</h3>
                    <button type="button" id="modal-fechar" class="p-1 text-gray-400 hover:text-gray-600 rounded">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    @if($outbound)
                        @php
                            $obConnsModal = is_array($outbound->connection) ? $outbound->connection : [];
                            $obStopsModal = max(0, count($obConnsModal) - 1);
                        @endphp
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="bg-slate-200 text-slate-700 text-xs font-semibold px-2 py-0.5 rounded">IDA</span>
                                <span class="text-sm text-gray-500 uppercase">{{ $outbound->cia }}</span>
                                @if($outbound->flight_number)
                                    <span class="text-sm text-gray-500">{{ $outbound->flight_number }}</span>
                                @endif
                                <span class="text-xs px-1.5 py-0.5 rounded font-medium {{ $obStopsModal > 0 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' }}">
                                    {{ $obStopsModal > 0 ? $obStopsModal . ' conexão' : 'Direto' }}
                                </span>
                            </div>
                            @if($order->flightSearch && $order->flightSearch->outbound_date)
                                <p class="text-xs font-medium text-gray-600 mb-1">{{ $order->flightSearch->outbound_date->format('d/m/Y') }}</p>
                            @endif
                            <div class="flex items-center justify-between text-sm">
                                <div>
                                    <p class="font-medium text-gray-800">{{ $outbound->departure_location }}</p>
                                    <p class="text-gray-500">{{ $outbound->departure_time }}</p>
                                </div>
                                <div class="flex-1 mx-3 text-center">
                                    @if($outbound->total_flight_duration)
                                        <span class="text-gray-400">{{ $outbound->total_flight_duration }}</span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-gray-800">{{ $outbound->arrival_location }}</p>
                                    <p class="text-gray-500">{{ $outbound->arrival_time }}</p>
                                </div>
                            </div>
                            @if($obStopsModal > 0)
                                <div class="mt-3 pt-3 border-t border-gray-200">
                                    @include('partials._connection_details', ['segments' => $obConnsModal, 'accentColor' => 'blue', 'compact' => false])
                                </div>
                            @endif
                        </div>
                    @endif
                    @if($inbound)
                        @php
                            $ibConnsModal = is_array($inbound->connection) ? $inbound->connection : [];
                            $ibStopsModal = max(0, count($ibConnsModal) - 1);
                        @endphp
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="bg-slate-200 text-slate-700 text-xs font-semibold px-2 py-0.5 rounded">VOLTA</span>
                                <span class="text-sm text-gray-500 uppercase">{{ $inbound->cia }}</span>
                                @if($inbound->flight_number)
                                    <span class="text-sm text-gray-500">{{ $inbound->flight_number }}</span>
                                @endif
                                <span class="text-xs px-1.5 py-0.5 rounded font-medium {{ $ibStopsModal > 0 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' }}">
                                    {{ $ibStopsModal > 0 ? $ibStopsModal . ' conexão' : 'Direto' }}
                                </span>
                            </div>
                            @if($order->flightSearch && $order->flightSearch->inbound_date)
                                <p class="text-xs font-medium text-gray-600 mb-1">{{ $order->flightSearch->inbound_date->format('d/m/Y') }}</p>
                            @endif
                            <div class="flex items-center justify-between text-sm">
                                <div>
                                    <p class="font-medium text-gray-800">{{ $inbound->departure_location }}</p>
                                    <p class="text-gray-500">{{ $inbound->departure_time }}</p>
                                </div>
                                <div class="flex-1 mx-3 text-center">
                                    @if($inbound->total_flight_duration)
                                        <span class="text-gray-400">{{ $inbound->total_flight_duration }}</span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-gray-800">{{ $inbound->arrival_location }}</p>
                                    <p class="text-gray-500">{{ $inbound->arrival_time }}</p>
                                </div>
                            </div>
                            @if($ibStopsModal > 0)
                                <div class="mt-3 pt-3 border-t border-gray-200">
                                    @include('partials._connection_details', ['segments' => $ibConnsModal, 'accentColor' => 'blue', 'compact' => false])
                                </div>
                            @endif
                        </div>
                    @endif
                    <div class="pt-4 border-t border-gray-200 space-y-2">
                        <div class="flex justify-between text-gray-600">
                            <span>Passagens</span>
                            <span>R$ {{ number_format($subtotalPassagens, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Taxas</span>
                            <span>R$ {{ number_format($subtotalTaxas, 2, ',', '.') }}</span>
                        </div>
                        <div id="modal-desconto-row" class="hidden flex justify-between items-center text-emerald-600 bg-emerald-50 -mx-2 px-2 py-1.5 rounded-lg">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                Cupom de desconto
                            </span>
                            <span id="modal-desconto-valor" class="font-semibold"></span>
                        </div>
                        <div id="modal-wallet-row" class="hidden flex justify-between items-center text-emerald-600 bg-emerald-50 -mx-2 px-2 py-1.5 rounded-lg">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                Créditos de indicação
                            </span>
                            <span id="modal-wallet-valor" class="font-semibold"></span>
                        </div>
                        <div id="modal-pix-discount-row" class="hidden flex justify-between items-center text-emerald-600 bg-emerald-50 -mx-2 px-2 py-1.5 rounded-lg">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Desconto PIX ({{ number_format($pixDiscount ?? 0, 0) }}%)
                            </span>
                            <span id="modal-pix-discount-valor" class="font-semibold"></span>
                        </div>
                        <div id="modal-juros-row" class="hidden flex justify-between text-amber-600">
                            <span>Juros do parcelamento</span>
                            <span id="modal-juros-valor" class="font-medium"></span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                            <span class="font-medium text-gray-700">Total</span>
                            <span id="modal-total" class="text-xl font-bold text-gray-900">R$ {{ number_format($orderTotal, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
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
        const modal = document.getElementById('modal-detalhes');
        const btnDetalhes = document.getElementById('btn-detalhes-compra');
        const modalFechar = document.getElementById('modal-fechar');
        const modalBackdrop = document.getElementById('modal-backdrop');

        function abrirModal() {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function fecharModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        btnDetalhes.addEventListener('click', abrirModal);
        modalFechar.addEventListener('click', fecharModal);
        modalBackdrop.addEventListener('click', fecharModal);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) fecharModal();
        });

        const cardFields = document.getElementById('card-fields');
        const paymentRadios = document.querySelectorAll('.payment-method-radio');
        const cardInputs = document.querySelectorAll('.card-input');

        function toggleCardFields() {
            const selected = document.querySelector('input[name="payment_method"]:checked');
            const isCc = selected && selected.value === 'credit_card';
            if (isCc) {
                cardFields.classList.remove('hidden');
                cardInputs.forEach(i => { if (!i.closest('[data-optional]')) i.required = true; });
            } else {
                cardFields.classList.add('hidden');
                cardInputs.forEach(i => { i.required = false; });
            }
            atualizarTotalFooter();
        }

        let appliedDiscount = 0;
        let cumulativeWithPix = true;
        const pixDiscountPct = {{ $pixDiscount ?? 0 }};
        const walletBalance = {{ $walletBalance ?? 0 }};

        function atualizarTotalFooter() {
            const footerTotal = document.getElementById('footer-total');
            const modalTotal = document.getElementById('modal-total');
            const modalJurosRow = document.getElementById('modal-juros-row');
            const modalJurosValor = document.getElementById('modal-juros-valor');
            const modalDescontoRow = document.getElementById('modal-desconto-row');
            const modalDescontoValor = document.getElementById('modal-desconto-valor');
            const modalPixDiscRow = document.getElementById('modal-pix-discount-row');
            const modalPixDiscValor = document.getElementById('modal-pix-discount-valor');
            const modalWalletRow = document.getElementById('modal-wallet-row');
            const modalWalletValor = document.getElementById('modal-wallet-valor');
            const baseTotal = parseFloat(footerTotal.dataset.base || 0);
            const totalComDesconto = baseTotal - appliedDiscount;
            const isCreditCard = document.querySelector('input[name="payment_method"]:checked')?.value === 'credit_card';
            const isPix = document.querySelector('input[name="payment_method"]:checked')?.value === 'pix';
            const fmt = v => 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            if (appliedDiscount > 0) {
                if (modalDescontoRow) modalDescontoRow.classList.remove('hidden');
                if (modalDescontoValor) modalDescontoValor.textContent = '- ' + fmt(appliedDiscount);
            } else {
                if (modalDescontoRow) modalDescontoRow.classList.add('hidden');
            }

            const walletToggle = document.getElementById('use_wallet_toggle');
            let walletUsed = 0;
            if (walletToggle && walletToggle.checked && walletBalance > 0) {
                walletUsed = Math.min(walletBalance, totalComDesconto);
            }
            const totalAfterWallet = totalComDesconto - walletUsed;

            if (walletUsed > 0) {
                if (modalWalletRow) modalWalletRow.classList.remove('hidden');
                if (modalWalletValor) modalWalletValor.textContent = '- ' + fmt(walletUsed);
            } else {
                if (modalWalletRow) modalWalletRow.classList.add('hidden');
            }

            let pixDiscountVal = 0;
            const canApplyPix = appliedDiscount > 0 ? cumulativeWithPix : true;
            if (isPix && pixDiscountPct > 0 && canApplyPix) {
                pixDiscountVal = totalAfterWallet * (pixDiscountPct / 100);
                if (modalPixDiscRow) modalPixDiscRow.classList.remove('hidden');
                if (modalPixDiscValor) modalPixDiscValor.textContent = '- ' + fmt(pixDiscountVal);
            } else {
                if (modalPixDiscRow) modalPixDiscRow.classList.add('hidden');
            }

            const footerDiscountInfo = document.getElementById('footer-discount-info');
            const discountParts = [];
            if (appliedDiscount > 0) discountParts.push('Cupom - ' + fmt(appliedDiscount));
            if (walletUsed > 0) discountParts.push('Créditos - ' + fmt(walletUsed));
            if (isPix && pixDiscountVal > 0) discountParts.push('PIX -' + pixDiscountPct + '%');

            if (!isCreditCard) {
                const totalPix = totalAfterWallet - pixDiscountVal;
                footerTotal.textContent = fmt(Math.max(totalPix, 0));
                if (modalTotal) modalTotal.textContent = fmt(Math.max(totalPix, 0));
                if (modalJurosRow) modalJurosRow.classList.add('hidden');

                if (footerDiscountInfo) {
                    if (discountParts.length > 0) {
                        footerDiscountInfo.textContent = discountParts.join(' | ') + ' aplicado(s)';
                        footerDiscountInfo.classList.remove('hidden');
                    } else {
                        footerDiscountInfo.classList.add('hidden');
                    }
                }
                return;
            }

            const installmentsSelect = document.getElementById('installments');
            const selectedOption = installmentsSelect?.options[installmentsSelect.selectedIndex];
            const rateDataTotal = selectedOption?.dataset.total ? parseFloat(selectedOption.dataset.total) : baseTotal;
            const rate = baseTotal > 0 ? (rateDataTotal / baseTotal) : 1;
            const totalComJuros = totalAfterWallet * rate;
            const juros = totalComJuros - totalAfterWallet;

            footerTotal.textContent = fmt(Math.max(totalComJuros, 0));
            if (modalTotal) modalTotal.textContent = fmt(Math.max(totalComJuros, 0));
            if (juros > 0.01) {
                if (modalJurosRow) modalJurosRow.classList.remove('hidden');
                if (modalJurosValor) modalJurosValor.textContent = fmt(juros);
            } else {
                if (modalJurosRow) modalJurosRow.classList.add('hidden');
            }

            if (footerDiscountInfo) {
                if (discountParts.length > 0) {
                    footerDiscountInfo.textContent = discountParts.join(' | ') + ' aplicado(s)';
                    footerDiscountInfo.classList.remove('hidden');
                } else {
                    footerDiscountInfo.classList.add('hidden');
                }
            }
        }

        paymentRadios.forEach(r => r.addEventListener('change', toggleCardFields));
        toggleCardFields();

        document.getElementById('installments')?.addEventListener('change', atualizarTotalFooter);

        (function setupCoupon() {
            const btnApply = document.getElementById('btn-apply-coupon');
            const btnRemove = document.getElementById('btn-remove-coupon');
            const couponInput = document.getElementById('coupon_input');
            const couponHidden = document.getElementById('coupon_code');
            const feedback = document.getElementById('coupon-feedback');
            const successBox = document.getElementById('coupon-success');
            const errorBox = document.getElementById('coupon-error');
            const errorMsg = document.getElementById('coupon-error-msg');
            const appliedCode = document.getElementById('coupon-applied-code');
            const appliedDisc = document.getElementById('coupon-applied-discount');
            const successMsg = document.getElementById('coupon-success-msg');
            const walletToggle = document.getElementById('use_wallet_toggle');
            const couponSection = document.getElementById('coupon-section');
            const walletSection = document.getElementById('wallet-section');
            const fmt = v => 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            if (walletToggle) {
                walletToggle.addEventListener('change', function () {
                    if (this.checked) {
                        couponInput.value = '';
                        couponHidden.value = '';
                        couponInput.disabled = true;
                        btnApply.disabled = true;
                        feedback.classList.add('hidden');
                        appliedDiscount = 0;
                        if (couponSection) couponSection.style.opacity = '0.5';
                    } else {
                        couponInput.disabled = false;
                        btnApply.disabled = false;
                        if (couponSection) couponSection.style.opacity = '1';
                    }
                    atualizarTotalFooter();
                });
            }

            btnApply.addEventListener('click', async function () {
                const code = couponInput.value.trim();
                if (!code) return;

                btnApply.disabled = true;
                btnApply.textContent = '...';

                try {
                    const payerDoc = document.getElementById('payer_document')?.value || '';
                    const res = await fetch('{{ route("checkout.apply-coupon", $order->token) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ coupon_code: code, payer_document: payerDoc }),
                    });

                    const data = await res.json();
                    feedback.classList.remove('hidden');

                    if (data.success) {
                        successBox.classList.remove('hidden');
                        errorBox.classList.add('hidden');
                        appliedCode.textContent = data.coupon_code;
                        appliedDisc.textContent = '- ' + fmt(data.discount_amount);
                        if (successMsg) {
                            const label = data.type === 'referral' ? 'Indicação' : 'Cupom';
                            successMsg.innerHTML = label + ' <strong>' + data.coupon_code + '</strong> aplicado: <strong>- ' + fmt(data.discount_amount) + '</strong>';
                        }
                        couponHidden.value = data.coupon_code;
                        couponInput.disabled = true;
                        appliedDiscount = data.discount_amount;
                        cumulativeWithPix = data.cumulative_with_pix !== false;
                        if (walletToggle) {
                            walletToggle.checked = false;
                            walletToggle.disabled = true;
                        }
                        atualizarTotalFooter();
                    } else {
                        errorBox.classList.remove('hidden');
                        successBox.classList.add('hidden');
                        errorMsg.textContent = data.message;
                    }
                } catch (e) {
                    errorBox.classList.remove('hidden');
                    successBox.classList.add('hidden');
                    errorMsg.textContent = 'Erro ao verificar código.';
                }

                btnApply.disabled = false;
                btnApply.textContent = 'Aplicar';
            });

            btnRemove.addEventListener('click', function () {
                couponHidden.value = '';
                couponInput.value = '';
                couponInput.disabled = false;
                feedback.classList.add('hidden');
                successBox.classList.add('hidden');
                errorBox.classList.add('hidden');
                appliedDiscount = 0;
                cumulativeWithPix = true;
                if (walletToggle) walletToggle.disabled = false;
                atualizarTotalFooter();
            });
        })();

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

        const isMercosul = @json($isMercosul);

        function updatePassengerFields(index) {
            const natSelect = document.getElementById('passengers_' + index + '_nationality');
            if (!natSelect) return;
            const nationality = natSelect.value;
            const isBR = nationality === 'BR';
            const needsPassport = !isBR && !isMercosul;

            const cpfWrapper = document.getElementById('passengers_' + index + '_cpf_wrapper');
            const passportWrapper = document.getElementById('passengers_' + index + '_passport_wrapper');
            const passportExpiryWrapper = document.getElementById('passengers_' + index + '_passport_expiry_wrapper');
            const cpfInput = document.getElementById('passengers_' + index + '_document');
            const passportInput = document.getElementById('passengers_' + index + '_passport_number');
            const passportExpiryInput = document.getElementById('passengers_' + index + '_passport_expiry');

            if (isBR) {
                cpfWrapper.classList.remove('hidden');
                cpfInput.required = true;
                cpfInput.setAttribute('data-validate', 'cpf');
                cpfInput.setAttribute('data-mask', 'cpf');
            } else {
                cpfWrapper.classList.add('hidden');
                cpfInput.required = false;
                cpfInput.removeAttribute('data-validate');
                cpfInput.removeAttribute('data-mask');
                cpfInput.value = '';
                cpfInput.classList.remove('input-error');
                const cpfErr = cpfInput.nextElementSibling;
                if (cpfErr && cpfErr.classList.contains('error-msg')) {
                    cpfErr.textContent = '';
                    cpfErr.classList.remove('visible');
                }
            }

            if (needsPassport) {
                passportWrapper.classList.remove('hidden');
                passportExpiryWrapper.classList.remove('hidden');
                passportInput.required = true;
                passportExpiryInput.required = true;
                passportInput.setAttribute('data-validate', 'required');
                passportExpiryInput.setAttribute('data-validate', 'passport-expiry');
            } else {
                passportWrapper.classList.add('hidden');
                passportExpiryWrapper.classList.add('hidden');
                passportInput.required = false;
                passportExpiryInput.required = false;
                passportInput.removeAttribute('data-validate');
                passportExpiryInput.removeAttribute('data-validate');
                passportInput.value = '';
                passportExpiryInput.value = '';
                passportInput.classList.remove('input-error');
                passportExpiryInput.classList.remove('input-error');
                [passportInput, passportExpiryInput].forEach(function(inp) {
                    var err = inp.nextElementSibling;
                    if (err && err.classList.contains('error-msg')) {
                        err.textContent = '';
                        err.classList.remove('visible');
                    }
                });
            }
        }

        document.querySelectorAll('.nationality-select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                updatePassengerFields(this.dataset.index);
            });
        });

        for (var pi = 0; pi < {{ $order->passengers_count }}; pi++) {
            updatePassengerFields(pi);
        }

        (function setupSavedPassengers() {
            const selects = document.querySelectorAll('.saved-passenger-select');
            if (!selects.length) return;

            const usedIds = {};

            selects.forEach(function (select) {
                Array.from(select.options).forEach(function (opt) {
                    if (opt.value) opt.dataset.label = opt.textContent;
                });
            });

            function fillPassengerFields(index, option) {
                const prefix = 'passengers_' + index + '_';
                document.getElementById(prefix + 'full_name').value = option.dataset.name || '';
                document.getElementById(prefix + 'document').value = option.dataset.document || '';
                document.getElementById(prefix + 'birth_date').value = option.dataset.birth || '';
                document.getElementById(prefix + 'email').value = option.dataset.email || '';
                document.getElementById(prefix + 'phone').value = option.dataset.phone || '';

                var natSelect = document.getElementById(prefix + 'nationality');
                var nat = option.dataset.nationality || 'BR';
                if (natSelect) {
                    natSelect.value = nat;
                    natSelect.dispatchEvent(new Event('change'));
                }

                var passportInput = document.getElementById(prefix + 'passport_number');
                var passportExpiryInput = document.getElementById(prefix + 'passport_expiry');
                if (passportInput) passportInput.value = option.dataset.passport || '';
                if (passportExpiryInput) passportExpiryInput.value = option.dataset.passportExpiry || '';
            }

            function clearPassengerFields(index) {
                const prefix = 'passengers_' + index + '_';
                ['full_name', 'document', 'birth_date', 'email', 'phone', 'passport_number', 'passport_expiry'].forEach(function (field) {
                    var el = document.getElementById(prefix + field);
                    if (el) el.value = '';
                });
                var natSelect = document.getElementById(prefix + 'nationality');
                if (natSelect) {
                    natSelect.value = 'BR';
                    natSelect.dispatchEvent(new Event('change'));
                }
            }

            function syncDisabledOptions() {
                const allUsed = Object.values(usedIds);
                selects.forEach(function (select) {
                    const currentIdx = select.dataset.index;
                    Array.from(select.options).forEach(function (opt) {
                        if (!opt.value) return;
                        const isUsedElsewhere = allUsed.includes(opt.value) && usedIds[currentIdx] !== opt.value;
                        opt.disabled = isUsedElsewhere;
                        opt.textContent = isUsedElsewhere
                            ? opt.dataset.label + ' - já selecionado'
                            : opt.dataset.label;
                    });
                });
            }

            selects.forEach(function (select) {
                select.addEventListener('change', function () {
                    const idx = this.dataset.index;
                    const selectedOption = this.options[this.selectedIndex];

                    if (this.value) {
                        usedIds[idx] = this.value;
                        fillPassengerFields(idx, selectedOption);
                    } else {
                        delete usedIds[idx];
                        clearPassengerFields(idx);
                    }

                    syncDisabledOptions();
                });
            });
        })();

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

        const payerCopySelect = document.getElementById('payer_copy_from');
        if (payerCopySelect) {
            payerCopySelect.addEventListener('change', function () {
                const idx = this.value;
                if (idx === '') return;
                const nameInput = document.getElementById('passengers_' + idx + '_full_name');
                const emailInput = document.getElementById('passengers_' + idx + '_email');
                const docInput = document.getElementById('passengers_' + idx + '_document');
                const natSelect = document.getElementById('passengers_' + idx + '_nationality');
                if (nameInput) document.getElementById('payer_name').value = nameInput.value;
                if (emailInput) document.getElementById('payer_email').value = emailInput.value;
                if (natSelect && natSelect.value === 'BR' && docInput && docInput.value) {
                    document.getElementById('payer_document').value = docInput.value;
                }
            });
        }

        document.querySelectorAll('[data-mask="cep"]').forEach(input => {
            input.addEventListener('input', function () {
                let v = this.value.replace(/\D/g, '').slice(0, 8);
                if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
                this.value = v;
                if (v.replace(/\D/g, '').length === 8) fetchCep(v.replace(/\D/g, ''));
            });
        });

        function fetchCep(cep) {
            const loading = document.getElementById('cep-loading');
            const hint = document.getElementById('cep-hint');
            if (loading) loading.classList.remove('hidden');
            if (hint) { hint.classList.add('hidden'); hint.textContent = ''; }

            fetch('https://viacep.com.br/ws/' + cep + '/json/')
                .then(r => r.json())
                .then(data => {
                    if (loading) loading.classList.add('hidden');
                    if (data.erro) {
                        if (hint) { hint.textContent = 'CEP não encontrado. Preencha manualmente.'; hint.classList.remove('hidden'); }
                        return;
                    }
                    if (data.logradouro) document.getElementById('billing_street').value = data.logradouro;
                    if (data.bairro) document.getElementById('billing_neighborhood').value = data.bairro;
                    if (data.localidade) document.getElementById('billing_city').value = data.localidade;
                    if (data.uf) document.getElementById('billing_state').value = data.uf;
                })
                .catch(() => {
                    if (loading) loading.classList.add('hidden');
                    if (hint) { hint.textContent = 'Erro ao buscar CEP. Preencha manualmente.'; hint.classList.remove('hidden'); }
                });
        }

        document.querySelectorAll('[data-mask="phone"]').forEach(input => {
            input.addEventListener('input', function () {
                let v = this.value.replace(/\D/g, '').slice(0, 11);
                if (v.length > 6) {
                    v = '(' + v.slice(0, 2) + ') ' + v.slice(2, 7) + '-' + v.slice(7);
                } else if (v.length > 2) {
                    v = '(' + v.slice(0, 2) + ') ' + v.slice(2);
                } else if (v.length > 0) {
                    v = '(' + v;
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
                            else {
                                const birthDate = new Date(year, month - 1, day);
                                if (birthDate >= new Date()) error = 'A data de nascimento não pode ser futura.';
                            }
                        }
                        break;
                    case 'email':
                        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) error = 'E-mail inválido.';
                        break;
                    case 'phone':
                        if (val.replace(/\D/g, '').length < 10) error = 'Telefone inválido. Informe DDD + número.';
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
                    case 'passport-expiry':
                        if (!/^\d{2}\/\d{2}\/\d{4}$/.test(val)) {
                            error = 'Data incompleta. Use o formato dd/mm/aaaa.';
                        } else {
                            const pe = val.split('/');
                            const peDay = parseInt(pe[0]), peMonth = parseInt(pe[1]), peYear = parseInt(pe[2]);
                            if (peMonth < 1 || peMonth > 12) error = 'Mês inválido.';
                            else if (peDay < 1 || peDay > 31) error = 'Dia inválido.';
                            else {
                                const peDate = new Date(peYear, peMonth - 1, peDay);
                                if (peDate <= new Date()) error = 'A validade do passaporte deve ser uma data futura.';
                            }
                        }
                        break;
                    case 'cep':
                        if (val.replace(/\D/g, '').length !== 8) error = 'CEP inválido (8 dígitos).';
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
                if (this.closest('.hidden')) return;
                validateField(this);
            });
        });

        if (typeof AppmaxScripts !== 'undefined') {
            try {
                AppmaxScripts.init();
                const ip = AppmaxScripts.getIp ? AppmaxScripts.getIp() : null;
                if (ip) {
                    document.getElementById('client_ip').value = ip;
                }
            } catch (err) {
                console.warn('AppMax JS init error:', err);
            }
        }

        document.getElementById('checkout-form').addEventListener('submit', function (e) {
            const isCreditCard = document.querySelector('input[name="payment_method"]:checked')?.value === 'credit_card';
            let selector = '.v-input:not(.card-input):not(.billing-input)';
            if (isCreditCard) selector = '.v-input';
            const inputs = this.querySelectorAll(selector);
            let firstInvalid = null;

            inputs.forEach(input => {
                if (input.closest('.hidden')) return;
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
            } else {
                var selectedMethod = document.querySelector('input[name="payment_method"]:checked');
                var isPix = selectedMethod && selectedMethod.value === 'pix';
                showTravelLoading({
                    title: isPix ? 'Gerando seu PIX...' : 'Processando seu pagamento...',
                    messages: isPix
                        ? ['Gerando código de pagamento...', 'Preparando o QR Code...', 'Finalizando sua compra...', 'Quase pronto!']
                        : ['Validando dados do cartão...', 'Confirmando com a operadora...', 'Finalizando sua compra...', 'Quase pronto!'],
                    timeoutMs: 90000
                });
            }
        });
    </script>
@endsection
