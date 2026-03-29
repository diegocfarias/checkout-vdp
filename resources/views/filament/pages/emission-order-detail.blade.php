<x-filament-panels::page>
    @php
        $order = $this->order;
        $order->loadMissing(['flights', 'flightSearch', 'passengers', 'emission']);
        $flights = $order->flights;
        $cabin = match($order->cabin) {
            'EC' => 'Econômica',
            'EX' => 'Executiva',
            default => $order->cabin ? ucfirst($order->cabin) : '-',
        };
        $totalMiles = $flights->sum(fn($f) => (float)($f->price_miles ?? $f->miles_price ?? 0));
    @endphp

    <div class="space-y-6 max-w-4xl">

        {{-- Info do pedido --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Pedido</span>
                    <div class="text-lg font-bold text-gray-900 dark:text-white">{{ $order->tracking_code }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Rota</span>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ strtoupper($order->departure_iata) }} → {{ strtoupper($order->arrival_iata) }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Cabine</span>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $cabin }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Milhas totais</span>
                    <div class="text-lg font-bold text-blue-600">{{ $totalMiles > 0 ? number_format($totalMiles, 0, '', '.') : '-' }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Status</span>
                    <div>
                        @php
                            $statusLabel = match($order->status) {
                                'awaiting_emission' => 'Aguardando emissão',
                                'completed' => 'Emitido',
                                'cancelled' => 'Cancelado',
                                default => ucfirst($order->status),
                            };
                            $statusColor = match($order->status) {
                                'awaiting_emission' => 'bg-amber-100 text-amber-700',
                                'completed' => 'bg-green-100 text-green-700',
                                'cancelled' => 'bg-red-100 text-red-700',
                                default => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusColor }}">{{ $statusLabel }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card de voo para print --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                    Card para Emissão
                </h3>
                <span class="text-xs text-gray-400">Tire print desta área para enviar ao milheiro</span>
            </div>
            <div class="p-5" id="flight-card-area">
                @foreach($flights as $flight)
                    @php
                        $conns = is_array($flight->connection) ? $flight->connection : [];
                        $stops = count($conns) > 1 ? count($conns) - 1 : 0;
                        $stopsLabel = $stops === 0 ? 'Direto' : $stops . ' conexão' . ($stops > 1 ? 'es' : '');
                        $miles = $flight->price_miles ?? $flight->miles_price ?? null;
                        $dirLabel = $flight->direction === 'outbound' ? 'IDA' : 'VOLTA';
                        $dirColor = $flight->direction === 'outbound' ? '#2563eb' : '#7c3aed';

                        $flightDate = null;
                        if ($order->flightSearch) {
                            $flightDate = $flight->direction === 'outbound'
                                ? $order->flightSearch->outbound_date
                                : $order->flightSearch->inbound_date;
                        }

                        $cia = strtoupper(trim($flight->cia ?? ''));
                        $flightNum = $flight->flight_number ?? '';
                    @endphp

                    <div style="border:1px solid #e5e7eb; border-radius:12px; padding:20px 24px; margin-bottom:12px; background:#fff; font-family:'Inter',system-ui,sans-serif;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
                            <span style="display:inline-block; background:{{ $dirColor }}; color:#fff; font-size:11px; font-weight:700; padding:3px 10px; border-radius:6px; letter-spacing:0.5px;">{{ $dirLabel }}</span>
                            @if($flightDate)
                                <span style="font-size:13px; color:#6b7280;">{{ $flightDate->format('d/m/Y') }} ({{ $flightDate->translatedFormat('l') }})</span>
                            @endif
                        </div>

                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:12px;">
                            <span style="font-size:14px; font-weight:600; color:#374151;">{{ $cia }}</span>
                            @if($flightNum)
                                <span style="font-size:13px; color:#9ca3af;">{{ $flightNum }}</span>
                            @endif
                            <span style="color:#d1d5db;">·</span>
                            <span style="font-size:13px; color:#6b7280;">{{ $cabin }}</span>
                        </div>

                        <div style="display:flex; align-items:center; gap:16px; margin-bottom:6px;">
                            <div style="text-align:left;">
                                <div style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:0.3px;">{{ $flight->departure_location }}</div>
                                <div style="font-size:22px; font-weight:700; color:#111827;">{{ $flight->departure_time ?? '--:--' }}</div>
                                <div style="font-size:12px; color:#6b7280;">{{ $flight->departure_label ?? '' }}</div>
                            </div>

                            <div style="flex:1; text-align:center; position:relative;">
                                <div style="font-size:12px; color:#6b7280; margin-bottom:4px;">{{ $flight->total_flight_duration ?? '' }}</div>
                                <div style="height:1px; background:#d1d5db; position:relative;">
                                    <div style="position:absolute; right:-2px; top:-3px; width:0; height:0; border-left:6px solid #9ca3af; border-top:3px solid transparent; border-bottom:3px solid transparent;"></div>
                                </div>
                                <div style="font-size:11px; margin-top:4px; color:{{ $stops === 0 ? '#059669' : '#d97706' }}; font-weight:600;">{{ $stopsLabel }}</div>
                            </div>

                            <div style="text-align:right;">
                                <div style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:0.3px;">{{ $flight->arrival_location }}</div>
                                <div style="font-size:22px; font-weight:700; color:#111827;">{{ $flight->arrival_time ?? '--:--' }}</div>
                                <div style="font-size:12px; color:#6b7280;">{{ $flight->arrival_label ?? '' }}</div>
                            </div>
                        </div>

                        @if($stops > 0)
                            <div style="margin-top:10px; padding:10px 12px; background:#f9fafb; border-radius:8px; border:1px solid #f3f4f6;">
                                @foreach($conns as $i => $seg)
                                    <div style="font-size:12px; color:#374151; padding:2px 0;">
                                        <strong>{{ $seg['DEPARTURE_TIME'] ?? '' }}</strong> {{ $seg['DEPARTURE_LOCATION'] ?? '' }}
                                        →
                                        <strong>{{ $seg['ARRIVAL_TIME'] ?? '' }}</strong> {{ $seg['ARRIVAL_LOCATION'] ?? '' }}
                                        @if($seg['FLIGHT_NUMBER'] ?? null)
                                            <span style="color:#9ca3af;">({{ $seg['FLIGHT_NUMBER'] }})</span>
                                        @endif
                                        @if($seg['FLIGHT_DURATION'] ?? null)
                                            <span style="color:#9ca3af;">· {{ $seg['FLIGHT_DURATION'] }}</span>
                                        @endif
                                    </div>
                                    @if($i < count($conns) - 1 && ($seg['TIME_WAITING'] ?? null))
                                        <div style="font-size:11px; color:#d97706; padding:1px 0 1px 12px;">Espera {{ $seg['TIME_WAITING'] }} em {{ $seg['ARRIVAL_LOCATION'] ?? '' }}</div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @if($miles)
                            <div style="margin-top:12px; padding-top:12px; border-top:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between;">
                                <span style="font-size:13px; color:#6b7280;">Total de milhas</span>
                                <span style="font-size:18px; font-weight:700; color:#111827;">{{ number_format((float)$miles, 0, '', '.') }} milhas</span>
                            </div>
                        @endif
                    </div>
                @endforeach

                @if($totalMiles > 0 && $flights->count() > 1)
                    <div style="border:1px solid #dbeafe; border-radius:10px; padding:14px 20px; background:#eff6ff; display:flex; align-items:center; justify-content:space-between;">
                        <span style="font-size:14px; font-weight:600; color:#1e40af;">Total geral</span>
                        <span style="font-size:20px; font-weight:800; color:#1e40af;">{{ number_format($totalMiles, 0, '', '.') }} milhas</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Passageiros --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    Passageiros ({{ $order->passengers->count() }})
                </h3>
                <button type="button" id="copy-passengers-btn" onclick="copyPassengers()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Copiar dados
                </button>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($order->passengers as $i => $p)
                    @php
                        $doc = $p->document ? preg_replace('/\D/', '', $p->document) : null;
                        if ($doc && strlen($doc) === 11) {
                            $doc = substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2);
                        }
                    @endphp
                    <div class="p-4">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-blue-50 text-blue-600 text-xs font-bold dark:bg-blue-900/30 dark:text-blue-400">{{ $i + 1 }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ strtoupper($p->full_name ?? '-') }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                            <div>
                                <span class="text-gray-400 text-xs">CPF</span>
                                <div class="text-gray-700 dark:text-gray-300 font-mono">{{ $doc ?? '-' }}</div>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Nascimento</span>
                                <div class="text-gray-700 dark:text-gray-300">{{ $p->birth_date ? $p->birth_date->format('d/m/Y') : '-' }}</div>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">E-mail</span>
                                <div class="text-gray-700 dark:text-gray-300 break-all">{{ $p->email ?? '-' }}</div>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs">Telefone</span>
                                <div class="text-gray-700 dark:text-gray-300">{{ $p->phone ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Texto oculto para copiar --}}
        <pre id="passengers-copy-text" class="sr-only">@foreach($order->passengers as $i => $p)@php
$doc = $p->document ? preg_replace('/\D/', '', $p->document) : null;
if ($doc && strlen($doc) === 11) {
    $doc = substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2);
}
@endphp
Passageiro {{ $i + 1 }}:
Nome: {{ strtoupper($p->full_name ?? '-') }}
CPF: {{ $doc ?? '-' }}
Nascimento: {{ $p->birth_date ? $p->birth_date->format('d/m/Y') : '-' }}
E-mail: {{ $p->email ?? '-' }}
Telefone: {{ $p->phone ?? '-' }}
@endforeach</pre>

        {{-- Voltar --}}
        <div>
            <a href="{{ route('filament.admin.pages.emission-queue') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Voltar para a fila
            </a>
        </div>
    </div>

    <script>
        function copyPassengers() {
            var text = document.getElementById('passengers-copy-text').innerText.trim();
            navigator.clipboard.writeText(text).then(function() {
                var btn = document.getElementById('copy-passengers-btn');
                var original = btn.innerHTML;
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copiado!';
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                setTimeout(function() {
                    btn.innerHTML = original;
                    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
            });
        }
    </script>
</x-filament-panels::page>
