<x-filament-panels::page>
    @php $data = $this->getOrderData(); @endphp

    @if(!$data)
        <div class="text-center py-12">
            <p class="text-gray-500 text-lg">Pedido não encontrado.</p>
            <a href="{{ route('filament.admin.pages.emission-queue') }}" class="mt-4 inline-flex items-center text-sm text-blue-600 hover:text-blue-700">Voltar para a fila</a>
        </div>
    @else
    <div class="space-y-6 max-w-4xl">

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Pedido</span>
                    <div class="text-lg font-bold text-gray-900 dark:text-white">{{ $data['tracking_code'] }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Rota</span>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $data['route'] }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Cabine</span>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $data['cabin'] }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Milhas totais</span>
                    <div class="text-lg font-bold text-blue-600">{{ $data['total_miles'] > 0 ? number_format($data['total_miles'], 0, '', '.') : '-' }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Status</span>
                    @php
                        $statusColor = match($data['status']) {
                            'awaiting_emission' => 'bg-amber-100 text-amber-700',
                            'completed' => 'bg-green-100 text-green-700',
                            'cancelled' => 'bg-red-100 text-red-700',
                            default => 'bg-gray-100 text-gray-700',
                        };
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusColor }}">{{ $data['status_label'] }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Card para Emissão</h3>
                <span class="text-xs text-gray-400">Tire print desta área para enviar ao milheiro</span>
            </div>
            <div class="p-5" id="flight-card-area">
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
                            <span style="color:#d1d5db;">&middot;</span>
                            <span style="font-size:13px; color:#6b7280;">{{ $data['cabin'] }}</span>
                        </div>

                        <div style="display:flex; align-items:center; gap:16px; margin-bottom:6px;">
                            <div style="text-align:left;">
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

                            <div style="text-align:right;">
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
                                        &rarr;
                                        <strong>{{ $seg['ARRIVAL_TIME'] ?? '' }}</strong> {{ $seg['ARRIVAL_LOCATION'] ?? '' }}
                                        @if(!empty($seg['FLIGHT_NUMBER']))
                                            <span style="color:#9ca3af;">({{ $seg['FLIGHT_NUMBER'] }})</span>
                                        @endif
                                        @if(!empty($seg['FLIGHT_DURATION']))
                                            <span style="color:#9ca3af;">&middot; {{ $seg['FLIGHT_DURATION'] }}</span>
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

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Passageiros ({{ count($data['passengers']) }})</h3>
                <button type="button" id="copy-passengers-btn" onclick="copyPassengers()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                    Copiar dados
                </button>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($data['passengers'] as $pi => $pax)
                    <div class="p-4">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-blue-50 text-blue-600 text-xs font-bold dark:bg-blue-900/30 dark:text-blue-400">{{ $pi + 1 }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $pax['name'] }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                            <div>
                                <span class="text-gray-400 text-xs">CPF</span>
                                <div class="text-gray-700 dark:text-gray-300 font-mono">{{ $pax['cpf'] }}</div>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Nascimento</span>
                                <div class="text-gray-700 dark:text-gray-300">{{ $pax['birth_date'] }}</div>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">E-mail</span>
                                <div class="text-gray-700 dark:text-gray-300 break-all">{{ $pax['email'] }}</div>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Telefone</span>
                                <div class="text-gray-700 dark:text-gray-300">{{ $pax['phone'] }}</div>
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

        <div>
            <a href="{{ route('filament.admin.pages.emission-queue') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition">
                &larr; Voltar para a fila
            </a>
        </div>
    </div>

    <script>
        function copyPassengers() {
            var text = document.getElementById('passengers-copy-text').innerText.trim();
            navigator.clipboard.writeText(text).then(function() {
                var btn = document.getElementById('copy-passengers-btn');
                btn.innerText = 'Copiado!';
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-green-600');
                setTimeout(function() {
                    btn.innerText = 'Copiar dados';
                    btn.classList.remove('bg-green-600');
                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
            });
        }
    </script>
    @endif
</x-filament-panels::page>
