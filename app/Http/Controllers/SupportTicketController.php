<?php

namespace App\Http\Controllers;

use App\Mail\SupportTicketCreatedMail;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportTicketController extends Controller
{
    private const MAX_ATTACHMENTS = 5;

    private const MAX_ATTACHMENT_KB = 10240;

    private const ATTACHMENT_MIMES = 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip';

    public function index()
    {
        $customer = auth('customer')->user();
        $tickets = SupportTicket::where('customer_id', $customer->id)
            ->with('order')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('customer.support-index', compact('customer', 'tickets'));
    }

    public function show(SupportTicket $ticket)
    {
        $customer = auth('customer')->user();

        if ($ticket->customer_id !== $customer->id) {
            abort(404);
        }

        $ticket->load(['order', 'initialAttachments' => function ($q) {
            $q->visibleToCustomer()->orderBy('created_at');
        }, 'messages' => function ($q) {
            $q->where('is_internal_note', false)->orderBy('created_at');
        }, 'messages.user', 'messages.customer', 'messages.attachments' => function ($q) {
            $q->visibleToCustomer()->orderBy('created_at');
        }]);

        return view('customer.support-detail', compact('customer', 'ticket'));
    }

    public function store(Request $request)
    {
        $customer = auth('customer')->user();

        $validated = $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'subject' => 'required|in:' . implode(',', array_keys(SupportTicket::SUBJECTS)),
            'message' => 'required|string|max:5000',
            'attachments' => 'nullable|array|max:' . self::MAX_ATTACHMENTS,
            'attachments.*' => 'file|max:' . self::MAX_ATTACHMENT_KB . '|mimes:' . self::ATTACHMENT_MIMES,
        ]);

        if (! empty($validated['order_id'])) {
            $order = Order::find($validated['order_id']);
            if (! $order || $order->customer_id !== $customer->id) {
                abort(403);
            }
        }

        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'order_id' => $validated['order_id'] ?? null,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->storeUploadedAttachments($request, $ticket);

        try {
            Mail::to($customer->email)->send(new SupportTicketCreatedMail($ticket));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar email de confirmação do ticket', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('customer.support.show', $ticket)
            ->with('success', 'Sua solicitação foi aberta com sucesso! Responderemos em breve.');
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $customer = auth('customer')->user();

        if ($ticket->customer_id !== $customer->id) {
            abort(404);
        }

        if ($ticket->status === 'closed') {
            return back()->with('error', 'Este atendimento já foi encerrado.');
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:5000|required_without:attachments',
            'attachments' => 'nullable|array|max:' . self::MAX_ATTACHMENTS,
            'attachments.*' => 'file|max:' . self::MAX_ATTACHMENT_KB . '|mimes:' . self::ATTACHMENT_MIMES,
        ]);

        $messageText = trim((string) ($validated['message'] ?? ''));

        $message = $ticket->messages()->create([
            'customer_id' => $customer->id,
            'message' => $messageText !== '' ? $messageText : 'Anexo enviado.',
        ]);

        $this->storeUploadedAttachments($request, $ticket, $message);

        if ($ticket->status === 'awaiting_customer') {
            $ticket->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'Resposta enviada com sucesso!');
    }

    private function storeUploadedAttachments(Request $request, SupportTicket $ticket, ?SupportTicketMessage $message = null): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $customer = auth('customer')->user();

        foreach ($request->file('attachments', []) as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $path = $file->store('support-ticket-attachments/' . $ticket->uuid, 'local');

            $ticket->attachments()->create([
                'support_ticket_message_id' => $message?->id,
                'uploaded_by_customer_id' => $customer?->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'is_internal' => false,
            ]);
        }
    }
}
