<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketReplyMail extends Mailable
{
    use SerializesModels;

    public SupportTicket $ticket;

    public SupportTicketMessage $reply;

    public string $ticketUrl;

    public function __construct(SupportTicket $ticket, SupportTicketMessage $reply)
    {
        $this->ticket = $ticket;
        $this->reply = $reply;
        $this->ticketUrl = route('customer.support.show', $ticket);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Atendimento #{$this->ticket->id} - Nova resposta da equipe"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.support-reply');
    }
}
