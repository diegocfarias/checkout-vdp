@extends('layouts.public')

@section('title', 'Redefinir senha')

@section('content')
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-1">Definir nova senha</h2>
            <p class="text-sm text-gray-500 mb-6">Escolha uma senha segura para sua conta.</p>

            @error('email')
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                    {{ $message }}
                </div>
            @enderror

            <form method="POST" action="{{ route('customer.password.update') }}" data-validate-form>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="space-y-4">
                    <div class="field-group">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Nova senha</label>
                        <input type="password" name="password" id="password" required autofocus
                               data-validate="password"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-3 border @error('password') is-invalid @enderror">
                        <span class="error-msg">@error('password'){{ $message }}@enderror</span>
                        <div class="password-strength"><div class="password-strength-bar"></div></div>
                        <p class="password-strength-label"></p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmar senha</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" required
                               data-validate="password-confirm"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-3 border">
                        <span class="error-msg"></span>
                    </div>

                    <button type="submit"
                            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg transition-colors">
                        Definir senha
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials._form_validation')
@endpush
