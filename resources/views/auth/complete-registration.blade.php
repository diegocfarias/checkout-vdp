@extends('layouts.public')

@section('title', 'Completar cadastro')

@section('content')
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
            <div class="flex items-center gap-3 mb-4">
                @if($googleUser['avatar'] ?? null)
                    <img src="{{ $googleUser['avatar'] }}" alt="" class="w-10 h-10 rounded-full ring-2 ring-gray-100">
                @endif
                <div>
                    <p class="font-medium text-gray-800">{{ $googleUser['name'] }}</p>
                    <p class="text-sm text-gray-500">{{ $googleUser['email'] }}</p>
                </div>
            </div>

            <h2 class="text-xl font-bold text-gray-800 mb-1">Completar cadastro</h2>
            <p class="text-sm text-gray-500 mb-6">Precisamos de mais alguns dados para finalizar.</p>

            <form method="POST" action="{{ route('customer.complete-registration.submit') }}" data-validate-form>
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="document" class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                        <input type="text" name="document" id="document" value="{{ old('document') }}" required
                               placeholder="000.000.000-00" inputmode="numeric" maxlength="14"
                               data-mask="cpf" data-validate="cpf"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-2.5 border @error('document') is-invalid @enderror">
                        <span class="error-msg">@error('document'){{ $message }}@enderror</span>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                        <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" required
                               placeholder="(00) 00000-0000" inputmode="numeric" maxlength="15"
                               data-mask="phone" data-validate="phone"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-2.5 border @error('phone') is-invalid @enderror">
                        <span class="error-msg">@error('phone'){{ $message }}@enderror</span>
                    </div>

                    <button type="submit"
                            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg transition-colors">
                        Finalizar cadastro
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials._form_validation')
@endpush
