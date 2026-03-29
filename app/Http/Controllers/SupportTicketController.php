<?php

namespace App\Http\Controllers;

use App\Mail\SupportTicketCreatedMail;
use App\Models\Order;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportTicketController extends Controller
{
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

        $ticket->load(['order', 'messages' => function ($q) {
            $q->where('is_internal_note', false)->orderBy('created_at');
        }, 'messages.user', 'messages.customer']);

        return view('customer.support-detail', compact('customer', 'ticket'));
    }

    public function store(Request $request)
    {
        $customer = auth('customer')->user();

        $validated = $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'subject' => 'required|in:' . implode(',', array_keys(SupportTicket::SUBJECTS)),
            'message' => 'required|string|max:5000',
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
            'message' => 'required|string|max:5000',
        ]);

        $ticket->messages()->create([
            'customer_id' => $customer->id,
            'message' => $validated['message'],
        ]);

        if ($ticket->status === 'awaiting_customer') {
            $ticket->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'Resposta enviada com sucesso!');
    }
}
