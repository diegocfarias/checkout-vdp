<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #111827; padding: 24px; text-align: center; border-radius: 12px 12px 0 0;">
                            <img src="{{ asset('images/logo-vdp.png') }}" alt="Voe de Primeira" style="height: 40px;">
                        </td>
                    </tr>

                    {{-- Content --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 32px 24px;">
                            {{-- Status badge --}}
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-bottom: 24px;">
                                        @php
                                            $badgeColor = match($newStatus) {
                                                'awaiting_payment' => '#f59e0b',
                                                'awaiting_emission' => '#3b82f6',
                                                'completed' => '#10b981',
                                                'cancelled' => '#ef4444',
                                                default => '#6b7280',
                                            };
                                        @endphp
                                        <span style="display: inline-block; background-color: {{ $badgeColor }}; color: #ffffff; font-size: 14px; font-weight: 600; padding: 6px 16px; border-radius: 20px;">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            <h1 style="margin: 0 0 8px; font-size: 22px; color: #111827; text-align: center;">
                                Pedido {{ $order->tracking_code }}
                            </h1>
                            <p style="margin: 0 0 24px; font-size: 15px; color: #6b7280; text-align: center;">
                                @switch($newStatus)
                                    @case('awaiting_payment')
                                        Seu pedido está aguardando pagamento.
                                        @break
                                    @case('awaiting_emission')
                                        Seu pagamento foi confirmado! Estamos encaminhando para emissão.
                                        @break
                                    @case('completed')
                                        Suas passagens foram emitidas com sucesso!
                                        @break
                                    @case('cancelled')
                                        Seu pedido foi cancelado.
                                        @break
                                    @default
                                        O status do seu pedido foi atualizado.
                                @endswitch
                            </p>

                            {{-- Flights --}}
                            @php
                                $order->loadMissing('flights');
                                $outbound = $order->flights->firstWhere('direction', 'outbound');
                                $inbound = $order->flights->firstWhere('direction', 'inbound');
                            @endphp

                            @if($outbound)
                                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                    <tr>
                                        <td style="padding: 16px;">
                                            <span style="display: inline-block; background-color: #e2e8f0; color: #475569; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; margin-bottom: 8px;">IDA</span>
                                            <p style="margin: 8px 0 0; font-size: 14px; color: #374151;">
                                                {{ $outbound->departure_location }} &rarr; {{ $outbound->arrival_location }}
                                                @if($outbound->departure_label)
                                                    <span style="color: #9ca3af;"> &middot; {{ $outbound->departure_label }}</span>
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if($inbound)
                                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                    <tr>
                                        <td style="padding: 16px;">
                                            <span style="display: inline-block; background-color: #e2e8f0; color: #475569; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; margin-bottom: 8px;">VOLTA</span>
                                            <p style="margin: 8px 0 0; font-size: 14px; color: #374151;">
                                                {{ $inbound->departure_location }} &rarr; {{ $inbound->arrival_location }}
                                                @if($inbound->departure_label)
                                                    <span style="color: #9ca3af;"> &middot; {{ $inbound->departure_label }}</span>
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            {{-- Timeline --}}
                            @php
                                $order->loadMissing('statusHistories');
                                $histories = $order->statusHistories->sortBy('created_at');
                            @endphp

                            @if($histories->count() > 0)
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 24px;">
                                    <tr>
                                        <td>
                                            <h3 style="margin: 0 0 16px; font-size: 16px; color: #374151;">Histórico do pedido</h3>
                                        </td>
                                    </tr>
                                    @foreach($histories as $history)
                                        @php
                                            $dotColor = match($history->status) {
                                                'awaiting_emission', 'completed' => '#10b981',
                                                'cancelled' => '#ef4444',
                                                'awaiting_payment' => '#f59e0b',
                                                default => '#9ca3af',
                                            };
                                            $isLast = $loop->last;
                                        @endphp
                                        <tr>
                                            <td style="padding-left: 8px; padding-bottom: {{ $isLast ? '0' : '16px' }}; border-left: 2px solid {{ $isLast ? 'transparent' : '#e5e7eb' }};">
                                                <table cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="vertical-align: top; padding-right: 12px;">
                                                            <div style="width: 10px; height: 10px; border-radius: 50%; background-color: {{ $dotColor }}; margin-top: 4px; margin-left: -6px;"></div>
                                                        </td>
                                                        <td>
                                                            <p style="margin: 0; font-size: 14px; font-weight: 600; color: #374151;">{{ $history->description }}</p>
                                                            <p style="margin: 2px 0 0; font-size: 12px; color: #9ca3af;">{{ $history->created_at->format('d/m/Y H:i') }}</p>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            {{-- CTA Button --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 32px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $trackingUrl }}" style="display: inline-block; background-color: #059669; color: #ffffff; font-size: 15px; font-weight: 600; padding: 14px 32px; border-radius: 10px; text-decoration: none;">
                                            Acompanhar pedido
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #111827; padding: 20px 24px; text-align: center; border-radius: 0 0 12px 12px;">
                            <p style="margin: 0; font-size: 13px; color: #9ca3af;">
                                &copy; {{ date('Y') }} Voe de Primeira
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
