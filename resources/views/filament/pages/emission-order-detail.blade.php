<x-filament-panels::page>
    @php $data = $this->getOrderData(); @endphp

    @if(!$data)
        <div class="text-center py-12">
            <p class="text-gray-500 text-lg">Pedido não encontrado.</p>
            <a href="{{ route('filament.admin.pages.emission-queue') }}"
                class="mt-4 inline-flex items-center text-sm text-primary-600 hover:text-primary-700">
                ← Voltar para a fila
            </a>
        </div>
    @else
    <div class="space-y-6">

        {{-- Resumo do pedido --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Pedido</span>
                <div class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ $data['tracking_code'] }}</div>
            </div>
            <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Rota</span>
                <div class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ $data['route'] }}</div>
            </div>
            <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Cabine</span>
                <div class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ $data['cabin'] }}</div>
            </div>
            <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Milhas totais</span>
                <div class="text-lg font-bold text-primary-600 dark:text-primary-400 mt-1">
                    {{ $data['total_miles'] > 0 ? number_format($data['total_miles'], 0, '', '.') : '-' }}
                </div>
            </div>
            <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</span>
                @php
                    $sc = match($data['status']) {
                        'awaiting_emission' => 'bg-warning-50 text-warning-600 dark:bg-warning-400/10 dark:text-warning-400',
                        'completed' => 'bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-400',
                        'cancelled' => 'bg-danger-50 text-danger-600 dark:bg-danger-400/10 dark:text-danger-400',
                        default => 'bg-gray-50 text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
                    };
                @endphp
                <div class="mt-1">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $sc }}">
                        {{ $data['status_label'] }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Card para emissão --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.115 5.19l.319 1.913A6 6 0 008.11 10.36L9.75 12l-.387.775c-.217.433-.132.956.21 1.298l1.348 1.348c.21.21.329.497.329.795v1.089c0 .426.24.815.622 1.006l.153.076c.433.217.956.132 1.298-.21l.723-.723a8.7 8.7 0 002.288-4.042 1.087 1.087 0 00-.358-1.099l-1.33-1.108c-.251-.21-.582-.299-.905-.245l-1.17.195a1.125 1.125 0 01-.98-.314l-.295-.295a1.125 1.125 0 010-1.591l.13-.132a1.125 1.125 0 011.3-.21l.603.302a.809.809 0 001.086-1.086L14.25 7.5l1.256-.837a4.5 4.5 0 001.528-1.732l.146-.292M6.115 5.19A9 9 0 1017.18 4.64M6.115 5.19A8.965 8.965 0 0112 3c1.929 0 3.72.608 5.18 1.64"/></svg>
                    Card para Emissão
                </h3>
                <span class="text-xs text-gray-400 dark:text-gray-500">Tire print para enviar ao milheiro</span>
            </div>

            <div class="p-5 bg-white dark:bg-white" id="flight-card-area">
                @foreach($data['flights'] as $flight)
                    <div style="border:1px solid #e5e7eb; border-radius:12px; padding:20px 24px; margin-bottom:12px; background:#fff; font-family:'Inter',system-ui,sans-serif;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
                            <span style="display:inline-block; background:{{ $flight['dir_color'] }}; color:#fff; font-size:11px; font-weight:700; padding:3px 10px; border-radius:6px; letter-spacing:0.5px;">{{ $flight['dir_label'] }}</span>
                            @if($flight['date'])
                                <span style="font-size:13px; color:#6b7280;">{{ $flight['date'] }}</span>
                            @endif
                        </div>

                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:12px;">
                            <span style="font-size:14px; font-weight:600; color:#374151;">{{ $flight['cia'] }}</span>
                            @if($flight['flight_number'])
                                <span style="font-size:13px; color:#9ca3af;">{{ $flight['flight_number'] }}</span>
                            @endif
                            <span style="color:#d1d5db;">·</span>
                            <span style="font-size:13px; color:#6b7280;">{{ $data['cabin'] }}</span>
                        </div>

                        <div style="display:flex; align-items:center; gap:16px; margin-bottom:6px;">
                            <div style="text-align:left; min-width:100px;">
                                <div style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:0.3px;">{{ $flight['departure_location'] }}</div>
                                <div style="font-size:22px; font-weight:700; color:#111827;">{{ $flight['departure_time'] }}</div>
                                <div style="font-size:12px; color:#6b7280;">{{ $flight['departure_label'] }}</div>
                            </div>

                            <div style="flex:1; text-align:center; position:relative;">
                                <div style="font-size:12px; color:#6b7280; margin-bottom:4px;">{{ $flight['duration'] }}</div>
                                <div style="height:1px; background:#d1d5db; position:relative;">
                                    <div style="position:absolute; right:-2px; top:-3px; width:0; height:0; border-left:6px solid #9ca3af; border-top:3px solid transparent; border-bottom:3px solid transparent;"></div>
                                </div>
                                <div style="font-size:11px; margin-top:4px; color:{{ $flight['stops'] === 0 ? '#059669' : '#d97706' }}; font-weight:600;">{{ $flight['stops_label'] }}</div>
                            </div>

                            <div style="text-align:right; min-width:100px;">
                                <div style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:0.3px;">{{ $flight['arrival_location'] }}</div>
                                <div style="font-size:22px; font-weight:700; color:#111827;">{{ $flight['arrival_time'] }}</div>
                                <div style="font-size:12px; color:#6b7280;">{{ $flight['arrival_label'] }}</div>
                            </div>
                        </div>

                        @if($flight['stops'] > 0)
                            <div style="margin-top:10px; padding:10px 12px; background:#f9fafb; border-radius:8px; border:1px solid #f3f4f6;">
                                @foreach($flight['connections'] as $ci => $seg)
                                    <div style="font-size:12px; color:#374151; padding:2px 0;">
                                        <strong>{{ $seg['DEPARTURE_TIME'] ?? '' }}</strong> {{ $seg['DEPARTURE_LOCATION'] ?? '' }}
                                        →
                                        <strong>{{ $seg['ARRIVAL_TIME'] ?? '' }}</strong> {{ $seg['ARRIVAL_LOCATION'] ?? '' }}
                                        @if(!empty($seg['FLIGHT_NUMBER']))
                                            <span style="color:#9ca3af;">({{ $seg['FLIGHT_NUMBER'] }})</span>
                                        @endif
                                        @if(!empty($seg['FLIGHT_DURATION']))
                                            <span style="color:#9ca3af;">· {{ $seg['FLIGHT_DURATION'] }}</span>
                                        @endif
                                    </div>
                                    @if($ci < count($flight['connections']) - 1 && !empty($seg['TIME_WAITING']))
                                        <div style="font-size:11px; color:#d97706; padding:1px 0 1px 12px;">Espera {{ $seg['TIME_WAITING'] }} em {{ $seg['ARRIVAL_LOCATION'] ?? '' }}</div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @if($flight['miles'] > 0)
                            <div style="margin-top:12px; padding-top:12px; border-top:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between;">
                                <span style="font-size:13px; color:#6b7280;">Total de milhas</span>
                                <span style="font-size:18px; font-weight:700; color:#111827;">{{ number_format($flight['miles'], 0, '', '.') }} milhas</span>
                            </div>
                        @endif
                    </div>
                @endforeach

                @if($data['total_miles'] > 0 && count($data['flights']) > 1)
                    <div style="border:1px solid #dbeafe; border-radius:10px; padding:14px 20px; background:#eff6ff; display:flex; align-items:center; justify-content:space-between;">
                        <span style="font-size:14px; font-weight:600; color:#1e40af;">Total geral</span>
                        <span style="font-size:20px; font-weight:800; color:#1e40af;">{{ number_format($data['total_miles'], 0, '', '.') }} milhas</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Passageiros --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    Passageiros ({{ count($data['passengers']) }})
                </h3>
                <button type="button" id="copy-passengers-btn" onclick="copyPassengers()"
                    class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-500 transition shadow-sm">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                    Copiar dados
                </button>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($data['passengers'] as $pi => $pax)
                    <div class="px-5 py-4">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-primary-50 text-primary-600 text-xs font-bold dark:bg-primary-400/10 dark:text-primary-400">{{ $pi + 1 }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $pax['name'] }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm pl-10">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 text-xs font-medium">CPF</span>
                                <div class="text-gray-900 dark:text-white font-mono mt-0.5">{{ $pax['cpf'] }}</div>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 text-xs font-medium">Nascimento</span>
                                <div class="text-gray-900 dark:text-white mt-0.5">{{ $pax['birth_date'] }}</div>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 text-xs font-medium">E-mail</span>
                                <div class="text-gray-900 dark:text-white mt-0.5 break-all">{{ $pax['email'] }}</div>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 text-xs font-medium">Telefone</span>
                                <div class="text-gray-900 dark:text-white mt-0.5">{{ $pax['phone'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <pre id="passengers-copy-text" class="sr-only">@foreach($data['passengers'] as $pi => $pax)
Passageiro {{ $pi + 1 }}:
Nome: {{ $pax['name'] }}
CPF: {{ $pax['cpf'] }}
Nascimento: {{ $pax['birth_date'] }}
E-mail: {{ $pax['email'] }}
Telefone: {{ $pax['phone'] }}
@endforeach</pre>

        {{-- Voltar --}}
        <div>
            <a href="{{ route('filament.admin.pages.emission-queue') }}"
                class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                Voltar para a fila
            </a>
        </div>
    </div>

    <script>
        function copyPassengers() {
            var text = document.getElementById('passengers-copy-text').innerText.trim();
            navigator.clipboard.writeText(text).then(function() {
                var btn = document.getElementById('copy-passengers-btn');
                var originalHTML = btn.innerHTML;
                btn.innerHTML = '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg> Copiado!';
                btn.classList.remove('bg-primary-600', 'hover:bg-primary-500');
                btn.classList.add('bg-success-600');
                setTimeout(function() {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('bg-success-600');
                    btn.classList.add('bg-primary-600', 'hover:bg-primary-500');
                }, 2000);
            });
        }
    </script>
    @endif
</x-filament-panels::page>
