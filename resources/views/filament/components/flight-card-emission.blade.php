@php
    $order = $getRecord();
    $order->loadMissing(['flights', 'flightSearch', 'passengers']);
    $flights = $order->flights;
    $cabin = match($order->cabin) {
        'EC' => 'Econômica',
        'EX' => 'Executiva',
        default => $order->cabin ? ucfirst($order->cabin) : '-',
    };
@endphp

<div style="max-width:720px;">
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
            {{-- Header: direction badge + date --}}
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
                <span style="display:inline-block; background:{{ $dirColor }}; color:#fff; font-size:11px; font-weight:700; padding:3px 10px; border-radius:6px; letter-spacing:0.5px;">{{ $dirLabel }}</span>
                @if($flightDate)
                    <span style="font-size:13px; color:#6b7280;">{{ $flightDate->format('d/m/Y') }} ({{ $flightDate->translatedFormat('l') }})</span>
                @endif
            </div>

            {{-- Airline + cabin --}}
            <div style="display:flex; align-items:center; gap:6px; margin-bottom:12px;">
                <span style="font-size:14px; font-weight:600; color:#374151;">{{ $cia }}</span>
                @if($flightNum)
                    <span style="font-size:13px; color:#9ca3af;">{{ $flightNum }}</span>
                @endif
                <span style="color:#d1d5db;">·</span>
                <span style="font-size:13px; color:#6b7280;">{{ $cabin }}</span>
            </div>

            {{-- Route: origin -> destination --}}
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

            {{-- Connection segments --}}
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

            {{-- Miles --}}
            @if($miles)
                <div style="margin-top:12px; padding-top:12px; border-top:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-size:13px; color:#6b7280;">Total de milhas</span>
                    <span style="font-size:18px; font-weight:700; color:#111827;">{{ number_format((float)$miles, 0, '', '.') }} milhas</span>
                </div>
            @endif
        </div>
    @endforeach

    {{-- Total miles summary --}}
    @php
        $totalMiles = $flights->sum(function($f) {
            return (float)($f->price_miles ?? $f->miles_price ?? 0);
        });
    @endphp
    @if($totalMiles > 0 && $flights->count() > 1)
        <div style="border:1px solid #dbeafe; border-radius:10px; padding:14px 20px; background:#eff6ff; display:flex; align-items:center; justify-content:space-between;">
            <span style="font-size:14px; font-weight:600; color:#1e40af;">Total geral</span>
            <span style="font-size:20px; font-weight:800; color:#1e40af;">{{ number_format($totalMiles, 0, '', '.') }} milhas</span>
        </div>
    @endif
</div>
