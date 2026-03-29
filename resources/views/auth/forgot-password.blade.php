@extends('layouts.public')

@section('title', 'Esqueci minha senha')

@section('content')
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-1">Redefinir senha</h2>
            <p class="text-sm text-gray-500 mb-6">Informe seu e-mail para receber o link de redefinição.</p>

            @if(session('status'))
                <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('customer.password.email') }}" data-validate-form>
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                               data-validate="email"
                               class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-3 border @error('email') is-invalid @enderror">
                        <span class="error-msg">@error('email'){{ $message }}@enderror</span>
                    </div>

                    <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-colors">
                        Enviar link de redefinição
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                <a href="{{ route('customer.login') }}" class="text-blue-600 hover:text-blue-700 font-medium">Voltar ao login</a>
            </p>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials._form_validation')
@endpush
