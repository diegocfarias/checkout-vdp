@extends('layouts.public')

@section('title', 'Criar conta')

@section('content')
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-1">Criar sua conta</h2>
            <p class="text-sm text-gray-500 mb-6">Cadastre-se para acompanhar seus pedidos.</p>

            <a href="{{ route('customer.google') }}"
               class="w-full flex items-center justify-center gap-3 px-4 py-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition-colors mb-6">
                <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                Cadastrar com Google
            </a>

            <div class="relative mb-6">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                <div class="relative flex justify-center text-xs"><span class="bg-white px-3 text-gray-400 uppercase tracking-wide">ou</span></div>
            </div>

            <form method="POST" action="{{ route('customer.register.submit') }}" data-validate-form>
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome completo</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                               data-validate="name"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border @error('name') is-invalid @enderror">
                        <span class="error-msg">@error('name'){{ $message }}@enderror</span>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required
                               data-validate="email"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border @error('email') is-invalid @enderror">
                        <span class="error-msg">@error('email'){{ $message }}@enderror</span>
                    </div>

                    <div>
                        <label for="document" class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                        <input type="text" name="document" id="document" value="{{ old('document') }}" required
                               placeholder="000.000.000-00" inputmode="numeric" maxlength="14"
                               data-mask="cpf" data-validate="cpf"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border @error('document') is-invalid @enderror">
                        <span class="error-msg">@error('document'){{ $message }}@enderror</span>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                        <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" required
                               placeholder="(00) 00000-0000" inputmode="numeric" maxlength="15"
                               data-mask="phone" data-validate="phone"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border @error('phone') is-invalid @enderror">
                        <span class="error-msg">@error('phone'){{ $message }}@enderror</span>
                    </div>

                    <div class="field-group">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                        <input type="password" name="password" id="password" required
                               data-validate="password"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border @error('password') is-invalid @enderror">
                        <span class="error-msg">@error('password'){{ $message }}@enderror</span>
                        <div class="password-strength"><div class="password-strength-bar"></div></div>
                        <p class="password-strength-label"></p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmar senha</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" required
                               data-validate="password-confirm"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border">
                        <span class="error-msg"></span>
                    </div>

                    <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-colors">
                        Criar conta
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                Já tem uma conta? <a href="{{ route('customer.login') }}" class="text-blue-600 hover:text-blue-700 font-medium">Entrar</a>
            </p>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials._form_validation')
@endpush
