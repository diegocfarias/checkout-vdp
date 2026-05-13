<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Mail\SupportTicketReplyMail;
use App\Models\SupportTicket;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label('Responder')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('primary')
                ->modalHeading('Responder ticket')
                ->form([
                    Textarea::make('message')
                        ->label('Mensagem')
                        ->required(fn (Get $get): bool => empty($get('attachments')))
                        ->rows(5)
                        ->maxLength(5000),

                    FileUpload::make('attachments')
                        ->label('Anexos')
                        ->multiple()
                        ->maxFiles(5)
                        ->maxSize(10240)
                        ->disk('local')
                        ->directory(fn () => 'support-ticket-attachments/' . $this->record->uuid)
                        ->storeFileNamesIn('attachment_names')
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'text/plain',
                            'application/zip',
                            'application/x-zip-compressed',
                        ])
                        ->helperText('Até 5 arquivos de 10 MB. Anexos em notas internas não aparecem para o cliente.'),

                    Toggle::make('is_internal_note')
                        ->label('Nota interna')
                        ->helperText('Notas internas não são visíveis para o cliente e não enviam email.'),

                    Select::make('new_status')
                        ->label('Alterar status para')
                        ->options(SupportTicket::STATUSES)
                        ->default(fn () => $this->record->status),
                ])
                ->visible(fn () => $this->record->status !== 'closed')
                ->action(function (array $data): void {
                    $messageText = trim((string) ($data['message'] ?? ''));

                    $msg = $this->record->messages()->create([
                        'user_id' => auth()->id(),
                        'message' => $messageText !== '' ? $messageText : 'Anexo enviado.',
                        'is_internal_note' => $data['is_internal_note'] ?? false,
                    ]);

                    $this->storeAttachments($msg, $data);

                    $updates = [];

                    if (! empty($data['new_status'])) {
                        $updates['status'] = $data['new_status'];

                        if ($data['new_status'] === 'resolved') {
                            $updates['resolved_at'] = now();
                        }
                        if ($data['new_status'] === 'closed') {
                            $updates['closed_at'] = now();
                        }
                    }

                    if (! $this->record->first_response_at) {
                        $updates['first_response_at'] = now();
                    }

                    if (! $this->record->assigned_to) {
                        $updates['assigned_to'] = auth()->id();
                    }

                    if (! empty($updates)) {
                        $this->record->update($updates);
                    }

                    if (! ($data['is_internal_note'] ?? false)) {
                        try {
                            Mail::to($this->record->customer->email)
                                ->send(new SupportTicketReplyMail($this->record, $msg));
                        } catch (\Throwable $e) {
                            Log::error('Falha ao enviar email de resposta do ticket', [
                                'ticket_id' => $this->record->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    Notification::make()->title('Resposta enviada')->success()->send();
                    $this->refreshFormData(['status', 'first_response_at', 'assigned_to']);
                }),

            Actions\Action::make('assign')
                ->label('Atribuir')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->modalHeading('Atribuir atendente')
                ->form([
                    Select::make('assigned_to')
                        ->label('Atendente')
                        ->options(fn () => User::whereIn('role', ['admin', 'support'])->where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                ])
                ->fillForm(fn () => ['assigned_to' => $this->record->assigned_to])
                ->visible(fn () => auth()->user()?->isAdmin())
                ->action(function (array $data): void {
                    $this->record->update([
                        'assigned_to' => $data['assigned_to'],
                        'status' => $this->record->status === 'open' ? 'in_progress' : $this->record->status,
                    ]);
                    Notification::make()->title('Atendente atribuído')->success()->send();
                    $this->refreshFormData(['assigned_to', 'status']);
                }),

            Actions\Action::make('change_priority')
                ->label('Prioridade')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('warning')
                ->modalHeading('Alterar prioridade')
                ->form([
                    Select::make('priority')
                        ->label('Prioridade')
                        ->options(SupportTicket::PRIORITIES)
                        ->required(),
                ])
                ->fillForm(fn () => ['priority' => $this->record->priority])
                ->visible(fn () => auth()->user()?->isAdmin())
                ->action(function (array $data): void {
                    $this->record->update(['priority' => $data['priority']]);
                    Notification::make()->title('Prioridade alterada')->success()->send();
                    $this->refreshFormData(['priority']);
                }),

            Actions\Action::make('resolve')
                ->label('Resolver')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => in_array($this->record->status, ['open', 'in_progress', 'awaiting_customer', 'awaiting_internal']))
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                    ]);
                    Notification::make()->title('Ticket resolvido')->success()->send();
                    $this->refreshFormData(['status', 'resolved_at']);
                }),

            Actions\Action::make('close')
                ->label('Fechar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status !== 'closed')
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);
                    Notification::make()->title('Ticket fechado')->danger()->send();
                    $this->refreshFormData(['status', 'closed_at']);
                }),
        ];
    }

    private function storeAttachments($message, array $data): void
    {
        $paths = Arr::wrap($data['attachments'] ?? []);
        $names = Arr::wrap($data['attachment_names'] ?? []);

        foreach ($paths as $key => $path) {
            if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $this->record->attachments()->create([
                'support_ticket_message_id' => $message->id,
                'uploaded_by_user_id' => auth()->id(),
                'disk' => 'local',
                'path' => $path,
                'original_name' => $names[$key] ?? $names[$path] ?? basename($path),
                'mime_type' => Storage::disk('local')->mimeType($path),
                'size' => Storage::disk('local')->size($path),
                'is_internal' => (bool) ($data['is_internal_note'] ?? false),
            ]);
        }
    }
}
