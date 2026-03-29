<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketCreatedMail extends Mailable
{
    use SerializesModels;

    public SupportTicket $ticket;

    public string $ticketUrl;

    public function __construct(SupportTicket $ticket)
    {
        $this->ticket = $ticket;
        $this->ticketUrl = route('customer.support.show', $ticket);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Atendimento #{$this->ticket->id} - Recebemos sua solicitação"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.support-created');
    }
}
