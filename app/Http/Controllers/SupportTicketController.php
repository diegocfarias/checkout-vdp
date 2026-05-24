<?php

namespace App\Http\Controllers;

use App\Mail\SupportTicketCreatedMail;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\CancellationPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

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

    public function storeCancellation(Request $request, Order $order, CancellationPolicyService $cancellationPolicy)
    {
        $customer = auth('customer')->user();

        if ($order->customer_id !== $customer->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => ['required', Rule::in(array_keys(CancellationPolicyService::REASONS))],
            'message' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array|max:' . self::MAX_ATTACHMENTS,
            'attachments.*' => 'file|max:' . self::MAX_ATTACHMENT_KB . '|mimes:' . self::ATTACHMENT_MIMES,
        ]);

        $existingTicket = SupportTicket::where('customer_id', $customer->id)
            ->where('order_id', $order->id)
            ->where('subject', 'cancellation')
            ->open()
            ->latest()
            ->first();

        if ($existingTicket) {
            return redirect()->route('customer.support.show', $existingTicket)
                ->with('success', 'Já existe uma solicitação de cancelamento aberta para este pedido.');
        }

        $policy = $cancellationPolicy->evaluate($order, $validated['reason']);
        $message = $this->buildCancellationMessage($order, $policy, (string) ($validated['message'] ?? ''));

        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'subject' => 'cancellation',
            'message' => $message,
            'status' => 'open',
            'priority' => $policy['priority'],
            'cancellation_reason' => $validated['reason'],
            'cancellation_within_policy' => $policy['within_policy'],
            'cancellation_policy_snapshot' => $policy,
            'cancellation_requested_at' => now(),
        ]);

        $this->storeUploadedAttachments($request, $ticket);

        try {
            Mail::to($customer->email)->send(new SupportTicketCreatedMail($ticket));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar email de confirmação do cancelamento', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        $successMessage = $policy['within_policy']
            ? 'Solicitação de cancelamento aberta com prioridade. Nossa equipe vai tratar conforme as regras aplicáveis.'
            : 'Solicitação de cancelamento registrada. Como está fora do prazo de cancelamento sem custo, não há reembolso para cancelamento voluntário.';

        return redirect()->route('customer.support.show', $ticket)
            ->with('success', $successMessage);
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

    private function buildCancellationMessage(Order $order, array $policy, string $customerMessage): string
    {
        $lines = [
            'Solicitação de cancelamento',
            '',
            'Pedido: ' . $order->tracking_code,
            'Motivo: ' . $policy['reason_label'],
            'Enquadramento: ' . $policy['rule'],
            'Prioridade: ' . ($policy['within_policy'] ? 'Dentro das regras prioritárias' : 'Fora do prazo sem custo'),
        ];

        if ($policy['purchase_reference_at']) {
            $lines[] = 'Referência da compra/pagamento: ' . $policy['purchase_reference_at'];
        }

        if ($policy['first_departure_date']) {
            $lines[] = 'Primeiro embarque: ' . $policy['first_departure_date'];
        }

        $customerMessage = trim($customerMessage);
        if ($customerMessage !== '') {
            $lines[] = '';
            $lines[] = 'Detalhes informados pelo cliente:';
            $lines[] = $customerMessage;
        }

        return implode("\n", $lines);
    }
}
