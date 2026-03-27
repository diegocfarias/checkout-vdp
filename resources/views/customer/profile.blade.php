@extends('layouts.public')

@section('title', 'Meu Perfil')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Meu perfil</h1>
                <p class="text-sm text-gray-500">Gerencie seus dados pessoais.</p>
            </div>
            <a href="{{ route('customer.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Voltar
            </a>
        </div>

        @if(session('status'))
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('customer.profile.update') }}" data-validate-form class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
            @csrf
            @method('PUT')
            <h3 class="font-semibold text-gray-800 mb-4">Dados editáveis</h3>
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome completo</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $customer->name) }}" required
                           data-validate="name"
                           class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-2.5 border @error('name') is-invalid @enderror">
                    <span class="error-msg">@error('name'){{ $message }}@enderror</span>
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                    <input type="tel" name="phone" id="phone" value="{{ old('phone', $customer->phone) }}" required
                           placeholder="(00) 00000-0000" inputmode="numeric" maxlength="15"
                           data-mask="phone" data-validate="phone"
                           class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-2.5 border @error('phone') is-invalid @enderror">
                    <span class="error-msg">@error('phone'){{ $message }}@enderror</span>
                </div>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-6 rounded-lg transition-colors text-sm">
                    Salvar alterações
                </button>
            </div>
        </form>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Dados protegidos</h3>
            <p class="text-xs text-gray-400 mb-4">Para alterar estes dados, envie uma solicitação via atendimento.</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                    <div class="flex items-center gap-2">
                        <input type="text" value="{{ $customer->email }}" readonly
                               class="flex-1 rounded-lg bg-gray-50 border-gray-200 text-gray-500 text-sm px-3 py-2.5 border cursor-not-allowed">
                        <button type="button" onclick="openChangeModal('email', '{{ $customer->email }}')"
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 hover:text-gray-700 font-medium px-3 py-2 rounded-lg transition-colors whitespace-nowrap">
                            Solicitar alteração
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                    <div class="flex items-center gap-2">
                        @php
                            $cpf = $customer->document ?? '';
                            $cpfFormatted = strlen($cpf) === 11
                                ? substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2)
                                : $cpf;
                        @endphp
                        <input type="text" value="{{ $cpfFormatted }}" readonly
                               class="flex-1 rounded-lg bg-gray-50 border-gray-200 text-gray-500 text-sm px-3 py-2.5 border cursor-not-allowed">
                        <button type="button" onclick="openChangeModal('document', '{{ $cpfFormatted }}')"
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 hover:text-gray-700 font-medium px-3 py-2 rounded-lg transition-colors whitespace-nowrap">
                            Solicitar alteração
                        </button>
                    </div>
                </div>
            </div>

            @if($customer->google_id)
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <svg class="w-4 h-4" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                        Conta Google vinculada
                    </div>
                </div>
            @endif
        </div>

        @if($errors->has('field'))
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                {{ $errors->first('field') }}
            </div>
        @endif
    </div>

    {{-- Modal de solicitação de alteração --}}
    <div id="change-modal" class="fixed inset-0 z-50 hidden" role="dialog">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" id="change-modal-backdrop"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full transform transition-all">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800" id="change-modal-title">Solicitar alteração</h3>
                    <button type="button" id="change-modal-close" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('customer.change-request') }}" data-validate-form class="p-6">
                    @csrf
                    <input type="hidden" name="field" id="change-field">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor atual</label>
                            <input type="text" id="change-current" readonly class="w-full rounded-lg bg-gray-50 border-gray-200 text-gray-500 text-sm px-3 py-2.5 border cursor-not-allowed">
                        </div>
                        <div>
                            <label for="requested_value" class="block text-sm font-medium text-gray-700 mb-1">Novo valor desejado</label>
                            <input type="text" name="requested_value" id="change-new-value" required
                                   class="v-input w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-2.5 border">
                            <span class="error-msg"></span>
                        </div>
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Motivo <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <textarea name="reason" rows="2" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm px-3 py-2.5 border"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg transition-colors text-sm">
                            Enviar solicitação
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        var changeModal = document.getElementById('change-modal');
        function openChangeModal(field, currentValue) {
            document.getElementById('change-field').value = field;
            document.getElementById('change-current').value = currentValue;
            document.getElementById('change-new-value').value = '';
            document.getElementById('change-modal-title').textContent = 'Solicitar alteração de ' + (field === 'email' ? 'E-mail' : 'CPF');
            changeModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(function() { document.getElementById('change-new-value').focus(); }, 100);
        }
        function closeChangeModal() {
            changeModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        document.getElementById('change-modal-close').addEventListener('click', closeChangeModal);
        document.getElementById('change-modal-backdrop').addEventListener('click', closeChangeModal);
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeChangeModal(); });
    </script>
@endsection

@push('scripts')
    @include('partials._form_validation')
@endpush
