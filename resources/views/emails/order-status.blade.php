@php
    $order->loadMissing(['flights', 'passengers', 'statusHistories', 'flightSearch']);
    $outbound = $order->flights->firstWhere('direction', 'outbound');
    $inbound = $order->flights->firstWhere('direction', 'inbound');
    $passenger = $order->passengers->first();
    $histories = $order->statusHistories->sortBy('created_at');
    $firstName = $passenger ? explode(' ', trim($passenger->full_name))[0] : 'Cliente';
    $flightSearch = $order->flightSearch;

    $totalPrice = 0;
    foreach ($order->flights as $f) {
        $totalPrice += (float) ($f->money_price ?? 0) + (float) ($f->tax ?? 0);
    }

    $isPix = $payment && $payment->payment_method === 'pix';
    $isCard = $payment && $payment->payment_method !== 'pix';
    $pixEmv = $payment ? ($payment->gateway_response['pix_emv'] ?? $payment->payment_url ?? null) : null;
    $pixQr = $payment ? ($payment->gateway_response['pix_qrcode'] ?? null) : null;

    $badgeColor = match($newStatus) {
        'awaiting_payment' => '#f59e0b',
        'awaiting_emission' => '#3b82f6',
        'completed' => '#059669',
        'cancelled' => '#ef4444',
        default => '#6b7280',
    };

    $badgeIcon = match($newStatus) {
        'awaiting_payment' => '⏳',
        'awaiting_emission' => '✅',
        'completed' => '✈️',
        'cancelled' => '✕',
        default => '📋',
    };

    $whatsappNumber = \App\Models\Setting::get('whatsapp_number', '');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f0fdf4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0fdf4; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">

                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #064e3b 0%, #111827 100%); padding: 32px 24px; text-align: center; border-radius: 16px 16px 0 0;">
                            <img src="{{ asset('images/logo-vdp.png') }}" alt="Voe de Primeira" style="height: 36px; margin-bottom: 16px;">
                            <p style="margin: 0; font-size: 13px; color: #a7f3d0; letter-spacing: 0.5px;">ATUALIZAÇÃO DO SEU PEDIDO</p>
                        </td>
                    </tr>

                    {{-- Status Badge --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 32px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <div style="display: inline-block; background-color: {{ $badgeColor }}15; border: 1px solid {{ $badgeColor }}40; padding: 8px 24px; border-radius: 50px;">
                                            <span style="font-size: 14px; font-weight: 700; color: {{ $badgeColor }}; letter-spacing: 0.3px;">
                                                {{ $badgeIcon }} {{ strtoupper($statusLabel) }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Greeting + Message --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 24px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <h1 style="margin: 0 0 6px; font-size: 24px; color: #111827; font-weight: 700;">
                                Olá, {{ $firstName }}!
                            </h1>
                            <p style="margin: 0 0 4px; font-size: 15px; color: #6b7280; line-height: 1.6;">
                                @switch($newStatus)
                                    @case('awaiting_payment')
                                        Seu pedido <strong style="color: #111827;">{{ $order->tracking_code }}</strong> foi criado com sucesso e está aguardando pagamento.
                                        @break
                                    @case('awaiting_emission')
                                        Pagamento confirmado! Seu pedido <strong style="color: #111827;">{{ $order->tracking_code }}</strong> está sendo encaminhado para emissão das passagens.
                                        @break
                                    @case('completed')
                                        Suas passagens foram emitidas! Pedido <strong style="color: #111827;">{{ $order->tracking_code }}</strong> finalizado.
                                        @break
                                    @case('cancelled')
                                        Seu pedido <strong style="color: #111827;">{{ $order->tracking_code }}</strong> foi cancelado.
                                        @break
                                    @default
                                        O status do pedido <strong style="color: #111827;">{{ $order->tracking_code }}</strong> foi atualizado.
                                @endswitch
                            </p>
                        </td>
                    </tr>

                    {{-- PIX Payment Section --}}
                    @if($newStatus === 'awaiting_payment' && $isPix)
                    <tr>
                        <td style="background-color: #ffffff; padding: 24px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px;">
                                <tr>
                                    <td style="padding: 24px; text-align: center;">
                                        <p style="margin: 0 0 4px; font-size: 12px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 1px;">Pague via PIX</p>
                                        <p style="margin: 0 0 16px; font-size: 28px; font-weight: 800; color: #111827;">
                                            R$ {{ number_format($totalPrice, 2, ',', '.') }}
                                        </p>

                                        @if($pixQr)
                                        <div style="margin: 0 auto 16px; width: 200px; height: 200px; background-color: #ffffff; border-radius: 12px; padding: 12px; border: 1px solid #e5e7eb;">
                                            <img src="data:image/png;base64,{{ $pixQr }}" alt="QR Code PIX" style="width: 100%; height: 100%; display: block;">
                                        </div>
                                        @endif

                                        @if($pixEmv)
                                        <p style="margin: 0 0 8px; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase;">Código copia e cola</p>
                                        <div style="background-color: #ffffff; border: 1px dashed #d1d5db; border-radius: 8px; padding: 12px 16px; margin: 0 auto; max-width: 420px;">
                                            <p style="margin: 0; font-size: 11px; color: #374151; word-break: break-all; font-family: 'Courier New', monospace; line-height: 1.5;">{{ $pixEmv }}</p>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Credit Card Section --}}
                    @if($newStatus === 'awaiting_payment' && $isCard)
                    <tr>
                        <td style="background-color: #ffffff; padding: 24px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px;">
                                <tr>
                                    <td style="padding: 20px 24px; text-align: center;">
                                        <p style="margin: 0 0 4px; font-size: 12px; font-weight: 700; color: #2563eb; text-transform: uppercase; letter-spacing: 1px;">Pagamento com Cartão</p>
                                        <p style="margin: 0 0 4px; font-size: 28px; font-weight: 800; color: #111827;">
                                            R$ {{ number_format($payment->amount ?? $totalPrice, 2, ',', '.') }}
                                        </p>
                                        <p style="margin: 0; font-size: 13px; color: #6b7280;">
                                            Seu pagamento está sendo processado. Você receberá a confirmação em breve.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Payment Confirmed Section --}}
                    @if($newStatus === 'awaiting_emission' && $payment)
                    <tr>
                        <td style="background-color: #ffffff; padding: 24px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px;">
                                <tr>
                                    <td style="padding: 20px 24px; text-align: center;">
                                        <p style="margin: 0 0 4px; font-size: 12px; font-weight: 700; color: #2563eb; text-transform: uppercase; letter-spacing: 1px;">Pagamento Confirmado</p>
                                        <p style="margin: 0 0 4px; font-size: 28px; font-weight: 800; color: #111827;">
                                            R$ {{ number_format($payment->amount ?? $totalPrice, 2, ',', '.') }}
                                        </p>
                                        <p style="margin: 0; font-size: 13px; color: #6b7280;">
                                            via {{ $payment->payment_method === 'pix' ? 'PIX' : 'Cartão de Crédito' }}
                                            @if($payment->paid_at)
                                                em {{ $payment->paid_at->format('d/m/Y \à\s H:i') }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Flights Section --}}
                    @if($outbound || $inbound)
                    <tr>
                        <td style="background-color: #ffffff; padding: 24px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px;">Seus voos</p>

                            @if($outbound)
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 10px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td>
                                                    <span style="display: inline-block; background-color: #059669; color: #ffffff; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Ida</span>
                                                </td>
                                                @if($flightSearch && $flightSearch->outbound_date)
                                                <td align="right">
                                                    <span style="font-size: 13px; font-weight: 600; color: #374151;">{{ \Carbon\Carbon::parse($flightSearch->outbound_date)->format('d/m/Y') }}</span>
                                                </td>
                                                @endif
                                            </tr>
                                        </table>
                                        <p style="margin: 10px 0 2px; font-size: 16px; font-weight: 700; color: #111827;">
                                            {{ $outbound->departure_location }} → {{ $outbound->arrival_location }}
                                        </p>
                                        <p style="margin: 0; font-size: 13px; color: #6b7280;">
                                            {{ strtoupper($outbound->operator ?? $outbound->cia ?? '') }}
                                            @if($outbound->flight_number) · {{ $outbound->flight_number }} @endif
                                            @if($outbound->departure_time) · {{ $outbound->departure_time }} @endif
                                            @if($outbound->total_flight_duration) · {{ $outbound->total_flight_duration }} @endif
                                        </p>
                                        @if($newStatus === 'completed' && $outbound->loc)
                                        <p style="margin: 8px 0 0;">
                                            <span style="display: inline-block; background-color: #fef3c7; border: 1px solid #fbbf24; color: #92400e; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 6px; letter-spacing: 0.5px;">
                                                LOC: {{ $outbound->loc }}
                                            </span>
                                        </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            @endif

                            @if($inbound)
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td>
                                                    <span style="display: inline-block; background-color: #2563eb; color: #ffffff; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Volta</span>
                                                </td>
                                                @if($flightSearch && $flightSearch->inbound_date)
                                                <td align="right">
                                                    <span style="font-size: 13px; font-weight: 600; color: #374151;">{{ \Carbon\Carbon::parse($flightSearch->inbound_date)->format('d/m/Y') }}</span>
                                                </td>
                                                @endif
                                            </tr>
                                        </table>
                                        <p style="margin: 10px 0 2px; font-size: 16px; font-weight: 700; color: #111827;">
                                            {{ $inbound->departure_location }} → {{ $inbound->arrival_location }}
                                        </p>
                                        <p style="margin: 0; font-size: 13px; color: #6b7280;">
                                            {{ strtoupper($inbound->operator ?? $inbound->cia ?? '') }}
                                            @if($inbound->flight_number) · {{ $inbound->flight_number }} @endif
                                            @if($inbound->departure_time) · {{ $inbound->departure_time }} @endif
                                            @if($inbound->total_flight_duration) · {{ $inbound->total_flight_duration }} @endif
                                        </p>
                                        @if($newStatus === 'completed' && $inbound->loc)
                                        <p style="margin: 8px 0 0;">
                                            <span style="display: inline-block; background-color: #fef3c7; border: 1px solid #fbbf24; color: #92400e; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 6px; letter-spacing: 0.5px;">
                                                LOC: {{ $inbound->loc }}
                                            </span>
                                        </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            @endif
                        </td>
                    </tr>
                    @endif

                    {{-- Order Summary --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 24px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-top: 1px solid #f3f4f6; padding-top: 16px;">
                                <tr>
                                    <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Passageiros</td>
                                    <td align="right" style="font-size: 13px; color: #374151; font-weight: 600; padding: 4px 0;">
                                        {{ $order->total_adults }} adulto{{ $order->total_adults > 1 ? 's' : '' }}{{ $order->total_children > 0 ? ', ' . $order->total_children . ' criança' . ($order->total_children > 1 ? 's' : '') : '' }}{{ $order->total_babies > 0 ? ', ' . $order->total_babies . ' bebê' . ($order->total_babies > 1 ? 's' : '') : '' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Classe</td>
                                    <td align="right" style="font-size: 13px; color: #374151; font-weight: 600; padding: 4px 0;">
                                        {{ $order->cabin === 'EX' ? 'Executiva' : 'Econômica' }}
                                    </td>
                                </tr>
                                @if($totalPrice > 0)
                                <tr>
                                    <td style="font-size: 13px; color: #6b7280; padding: 8px 0 4px; border-top: 1px solid #f3f4f6;">Total</td>
                                    <td align="right" style="font-size: 18px; color: #059669; font-weight: 800; padding: 8px 0 4px; border-top: 1px solid #f3f4f6;">
                                        R$ {{ number_format($totalPrice, 2, ',', '.') }}
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    {{-- Completed: LOC reminder --}}
                    @if($newStatus === 'completed')
                    <tr>
                        <td style="background-color: #ffffff; padding: 20px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fefce8; border: 1px solid #fde68a; border-radius: 10px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="margin: 0 0 4px; font-size: 13px; font-weight: 700; color: #92400e;">Próximos passos</p>
                                        <p style="margin: 0; font-size: 13px; color: #78350f; line-height: 1.5;">
                                            Use o código LOC acima para fazer o check-in no site da companhia aérea. O check-in geralmente abre 48h antes do voo.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Cancelled: support info --}}
                    @if($newStatus === 'cancelled')
                    <tr>
                        <td style="background-color: #ffffff; padding: 20px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="margin: 0 0 4px; font-size: 13px; font-weight: 700; color: #991b1b;">Precisa de ajuda?</p>
                                        <p style="margin: 0; font-size: 13px; color: #7f1d1d; line-height: 1.5;">
                                            Se você não solicitou este cancelamento ou tem dúvidas, entre em contato conosco
                                            @if($whatsappNumber)
                                                pelo <a href="https://wa.me/{{ $whatsappNumber }}" style="color: #059669; font-weight: 600; text-decoration: underline;">WhatsApp</a>.
                                            @else
                                                pelo nosso suporte.
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Timeline --}}
                    @if($histories->count() > 0)
                    <tr>
                        <td style="background-color: #ffffff; padding: 24px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 16px; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px;">Histórico</p>
                            @foreach($histories as $history)
                                @php
                                    $dotColor = match($history->status) {
                                        'awaiting_emission', 'completed' => '#059669',
                                        'cancelled' => '#ef4444',
                                        'awaiting_payment' => '#f59e0b',
                                        default => '#d1d5db',
                                    };
                                @endphp
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: {{ $loop->last ? '0' : '4px' }};">
                                    <tr>
                                        <td width="20" valign="top" style="padding-top: 5px;">
                                            <div style="width: 8px; height: 8px; border-radius: 50%; background-color: {{ $dotColor }};"></div>
                                        </td>
                                        <td style="padding-bottom: 12px; {{ !$loop->last ? 'border-bottom: 1px solid #f3f4f6;' : '' }}">
                                            <p style="margin: 0; font-size: 13px; font-weight: 600; color: #374151;">{{ $history->description }}</p>
                                            <p style="margin: 2px 0 0; font-size: 11px; color: #9ca3af;">{{ $history->created_at->format('d/m/Y \à\s H:i') }}</p>
                                        </td>
                                    </tr>
                                </table>
                            @endforeach
                        </td>
                    </tr>
                    @endif

                    {{-- CTA Button --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 28px 32px 32px; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $trackingUrl }}" style="display: inline-block; background-color: #059669; color: #ffffff; font-size: 15px; font-weight: 700; padding: 14px 40px; border-radius: 10px; text-decoration: none; letter-spacing: 0.3px;">
                                            Acompanhar pedido →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #064e3b 0%, #111827 100%); padding: 24px; text-align: center; border-radius: 0 0 16px 16px;">
                            <p style="margin: 0 0 8px; font-size: 13px; color: #a7f3d0; font-weight: 500;">
                                Voe de Primeira
                            </p>
                            <p style="margin: 0; font-size: 11px; color: #6b7280;">
                                &copy; {{ date('Y') }} · Todos os direitos reservados
                            </p>
                            @if($whatsappNumber)
                            <p style="margin: 8px 0 0; font-size: 12px;">
                                <a href="https://wa.me/{{ $whatsappNumber }}" style="color: #6ee7b7; text-decoration: none; font-weight: 500;">Falar no WhatsApp</a>
                            </p>
                            @endif
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
