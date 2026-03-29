@php
    $ticket->loadMissing(['customer', 'order']);
    $customerName = $ticket->customer ? explode(' ', trim($ticket->customer->name))[0] : 'Cliente';
    $subjectLabel = \App\Models\SupportTicket::SUBJECTS[$ticket->subject] ?? $ticket->subject;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
    <div style="max-width:600px;margin:0 auto;padding:24px 16px;">

        <div style="text-align:center;margin-bottom:24px;">
            <h1 style="font-size:20px;color:#1f2937;margin:0;">{{ config('app.name') }}</h1>
        </div>

        <div style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <div style="background:#2563eb;padding:24px;text-align:center;">
                <div style="font-size:32px;margin-bottom:8px;">📩</div>
                <h2 style="color:#ffffff;font-size:18px;margin:0;">Recebemos sua solicitação</h2>
            </div>

            <div style="padding:24px;">
                <p style="font-size:15px;color:#374151;line-height:1.6;margin:0 0 16px;">
                    Olá, <strong>{{ $customerName }}</strong>!
                </p>
                <p style="font-size:14px;color:#6b7280;line-height:1.6;margin:0 0 20px;">
                    Sua solicitação de atendimento foi aberta com sucesso. Nossa equipe responderá em breve.
                </p>

                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:20px;">
                    <table style="width:100%;font-size:14px;color:#374151;">
                        <tr>
                            <td style="padding:4px 0;font-weight:600;width:120px;">Ticket:</td>
                            <td style="padding:4px 0;">#{{ $ticket->id }}</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0;font-weight:600;">Assunto:</td>
                            <td style="padding:4px 0;">{{ $subjectLabel }}</td>
                        </tr>
                        @if($ticket->order)
                        <tr>
                            <td style="padding:4px 0;font-weight:600;">Pedido:</td>
                            <td style="padding:4px 0;">{{ $ticket->order->tracking_code }}</td>
                        </tr>
                        @endif
                    </table>
                </div>

                <div style="background:#f0f9ff;border-left:4px solid #2563eb;border-radius:4px;padding:12px 16px;margin-bottom:20px;">
                    <p style="font-size:13px;color:#1e40af;margin:0;font-style:italic;">
                        "{{ \Illuminate\Support\Str::limit($ticket->message, 200) }}"
                    </p>
                </div>

                <div style="text-align:center;">
                    <a href="{{ $ticketUrl }}" style="display:inline-block;background:#2563eb;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;padding:12px 32px;border-radius:8px;">
                        Acompanhar atendimento
                    </a>
                </div>
            </div>
        </div>

        <div style="text-align:center;margin-top:24px;">
            <p style="font-size:12px;color:#9ca3af;margin:0;">
                {{ config('app.name') }} &mdash; Este é um email automático, não responda.
            </p>
        </div>
    </div>
</body>
</html>
