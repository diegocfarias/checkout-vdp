<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use Illuminate\Support\Facades\Storage;

class SupportTicketAttachmentController extends Controller
{
    public function customerView(SupportTicket $ticket, SupportTicketAttachment $attachment)
    {
        $this->authorizeCustomerAttachment($ticket, $attachment);

        if (! $attachment->is_previewable) {
            return $this->download($attachment);
        }

        return $this->inline($attachment);
    }

    public function customerDownload(SupportTicket $ticket, SupportTicketAttachment $attachment)
    {
        $this->authorizeCustomerAttachment($ticket, $attachment);

        return $this->download($attachment);
    }

    public function adminView(SupportTicketAttachment $attachment)
    {
        $this->authorizeAdminAttachment($attachment);

        if (! $attachment->is_previewable) {
            return $this->download($attachment);
        }

        return $this->inline($attachment);
    }

    public function adminDownload(SupportTicketAttachment $attachment)
    {
        $this->authorizeAdminAttachment($attachment);

        return $this->download($attachment);
    }

    private function authorizeCustomerAttachment(SupportTicket $ticket, SupportTicketAttachment $attachment): void
    {
        $customer = auth('customer')->user();

        if (
            ! $customer
            || $ticket->customer_id !== $customer->id
            || $attachment->support_ticket_id !== $ticket->id
            || $attachment->is_internal
        ) {
            abort(404);
        }
    }

    private function authorizeAdminAttachment(SupportTicketAttachment $attachment): void
    {
        $user = auth()->user();

        if (! $user || (! $user->isAdmin() && ! $user->isSupport())) {
            abort(403);
        }

        $ticket = $attachment->ticket;

        if (
            $user->isSupport()
            && ! $user->isAdmin()
            && $ticket->assigned_to !== $user->id
            && $ticket->assigned_to !== null
            && $ticket->status !== 'open'
        ) {
            abort(403);
        }
    }

    private function inline(SupportTicketAttachment $attachment)
    {
        $path = $this->path($attachment);
        $filename = str_replace(['"', "\r", "\n"], '', $attachment->original_name);

        return response()->file($path, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function download(SupportTicketAttachment $attachment)
    {
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function path(SupportTicketAttachment $attachment): string
    {
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->path($attachment->path);
    }
}
