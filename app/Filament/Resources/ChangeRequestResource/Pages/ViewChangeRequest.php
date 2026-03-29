<?php

namespace App\Filament\Resources\ChangeRequestResource\Pages;

use App\Filament\Resources\ChangeRequestResource;
use App\Services\CustomerService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewChangeRequest extends ViewRecord
{
    protected static string $resource = ChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Aprovar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprovar solicitação')
                ->modalDescription('O dado do cliente será alterado automaticamente.')
                ->visible(fn () => $this->record->isPending())
                ->action(function (): void {
                    $admin = auth()->user();
                    app(CustomerService::class)->applyChangeRequest($this->record, $admin);
                    Notification::make()->title('Solicitação aprovada e aplicada')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('reject')
                ->label('Rejeitar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading('Rejeitar solicitação')
                ->form([
                    Textarea::make('admin_notes')
                        ->label('Motivo da rejeição')
                        ->required()
                        ->maxLength(1000),
                ])
                ->visible(fn () => $this->record->isPending())
                ->action(function (array $data): void {
                    $admin = auth()->user();
                    $this->record->update([
                        'status' => 'rejected',
                        'admin_id' => $admin->id,
                        'admin_notes' => $data['admin_notes'],
                        'resolved_at' => now(),
                    ]);
                    Notification::make()->title('Solicitação rejeitada')->danger()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
