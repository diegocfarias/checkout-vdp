@php
    $ticket->loadMissing(['customer', 'order']);
    $customerName = $ticket->customer ? explode(' ', trim($ticket->customer->name))[0] : 'Cliente';
    $subjectLabel = \App\Models\SupportTicket::SUBJECTS[$ticket->subject] ?? $ticket->subject;
    $agentName = $reply->user?->name ?? 'Equipe de Suporte';
    $whatsappNumber = \App\Models\Setting::get('whatsapp_number', '');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f5f6f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f6f8; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 32px 24px 24px; text-align: center; border-radius: 16px 16px 0 0; border-bottom: 2px solid #d4a843;">
                            <img src="{{ asset('images/logo-vdp.png') }}" alt="Voe de Primeira" style="height: 36px; margin-bottom: 16px;">
                            <p style="margin: 0; font-size: 13px; color: #6b7280; letter-spacing: 0.5px; font-weight: 600;">RESPOSTA NO ATENDIMENTO</p>
                        </td>
                    </tr>

                    {{-- Badge --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 32px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <div style="display: inline-block; background-color: #d1fae5; border: 1px solid #6ee7b7; padding: 8px 24px; border-radius: 50px;">
                                            <span style="font-size: 14px; font-weight: 700; color: #059669; letter-spacing: 0.3px;">
                                                NOVA RESPOSTA
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Greeting --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 24px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <h1 style="margin: 0 0 6px; font-size: 24px; color: #111827; font-weight: 700;">
                                Olá, {{ $customerName }}!
                            </h1>
                            <p style="margin: 0; font-size: 15px; color: #6b7280; line-height: 1.6;">
                                {{ $agentName }} respondeu à sua solicitação sobre <strong style="color: #111827;">{{ $subjectLabel }}</strong>.
                            </p>
                        </td>
                    </tr>

                    {{-- Order Info --}}
                    @if($ticket->order)
                    <tr>
                        <td style="background-color: #ffffff; padding: 24px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="font-size: 13px; color: #6b7280; padding: 4px 0;">Ticket</td>
                                                <td align="right" style="font-size: 13px; color: #374151; font-weight: 600; padding: 4px 0;">#{{ $ticket->id }}</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 13px; color: #6b7280; padding: 4px 0; border-top: 1px solid #f3f4f6;">Pedido</td>
                                                <td align="right" style="font-size: 13px; color: #374151; font-weight: 600; padding: 4px 0; border-top: 1px solid #f3f4f6;">{{ $ticket->order->tracking_code }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Reply Message --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 24px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px;">Resposta da equipe</p>
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 8px; font-size: 12px; font-weight: 700; color: #2563eb;">{{ $agentName }} escreveu:</p>
                                        <p style="margin: 0; font-size: 14px; color: #1f2937; line-height: 1.7; white-space: pre-wrap;">{{ $reply->message }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Help Text --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 20px 32px 0; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fefce8; border: 1px solid #fde68a; border-radius: 10px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="margin: 0; font-size: 13px; color: #78350f; line-height: 1.5;">
                                            Você pode responder diretamente pelo site clicando no botão abaixo.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- CTA --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 28px 32px 32px; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $ticketUrl }}" style="display: inline-block; background-color: #059669; color: #ffffff; font-size: 15px; font-weight: 700; padding: 14px 40px; border-radius: 10px; text-decoration: none; letter-spacing: 0.3px;">
                                            Ver atendimento &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px; text-align: center; border-radius: 0 0 16px 16px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 8px; font-size: 13px; color: #374151; font-weight: 500;">
                                Voe de Primeira
                            </p>
                            <p style="margin: 0; font-size: 11px; color: #9ca3af;">
                                &copy; {{ date('Y') }} &middot; Todos os direitos reservados
                            </p>
                            @if($whatsappNumber)
                            <p style="margin: 8px 0 0; font-size: 12px;">
                                <a href="https://wa.me/{{ $whatsappNumber }}" style="color: #059669; text-decoration: none; font-weight: 500;">Falar no WhatsApp</a>
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
