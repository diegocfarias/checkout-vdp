<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\PaymentGatewayResolver;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('mark_completed')
                ->label('Marcar como Emitido')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading('Confirmar emissão')
                ->modalDescription('Informe o localizador (LOC) de cada trecho para o cliente realizar o check-in.')
                ->form(function (): array {
                    $this->record->loadMissing('flights');
                    $fields = [];
                    foreach ($this->record->flights as $flight) {
                        $dir = $flight->direction === 'outbound' ? 'Ida' : 'Volta';
                        $label = $dir . ' — ' . strtoupper($flight->cia ?? '') . ' (' . ($flight->departure_location ?? '') . ' → ' . ($flight->arrival_location ?? '') . ')';
                        $fields[] = TextInput::make('loc_' . $flight->id)
                            ->label($label)
                            ->placeholder('Ex: ABC123')
                            ->required()
                            ->maxLength(20)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase']);
                    }
                    return $fields;
                })
                ->visible(fn (): bool => $this->record->status === 'awaiting_emission')
                ->action(function (array $data): void {
                    $this->record->loadMissing('flights');
                    $locs = [];
                    foreach ($this->record->flights as $flight) {
                        $loc = strtoupper(trim($data['loc_' . $flight->id] ?? ''));
                        if ($loc) {
                            $flight->update(['loc' => $loc]);
                            $locs[] = $loc;
                        }
                    }
                    $this->record->update([
                        'status' => 'completed',
                        'loc' => implode(' / ', array_unique($locs)),
                    ]);
                    Notification::make()->title('Pedido emitido com sucesso')->success()->send();
                    $this->refreshFormData(['status', 'loc']);
                }),

            Actions\Action::make('mark_paid')
                ->label('Confirmar Pagamento')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Confirmar pagamento manualmente')
                ->modalDescription('Marcar este pedido como pago? Use quando o pagamento foi confirmado fora do sistema.')
                ->visible(fn (): bool => in_array($this->record->status, ['pending', 'awaiting_payment']))
                ->action(function (): void {
                    $now = now();
                    $payment = $this->record->latestPayment;
                    if ($payment) {
                        $payment->update(['status' => 'paid', 'paid_at' => $now]);
                    }
                    $this->record->update(['status' => 'awaiting_emission', 'paid_at' => $now]);
                    Notification::make()->title('Pagamento confirmado')->success()->send();
                    $this->refreshFormData(['status', 'paid_at']);
                }),

            Actions\Action::make('refund')
                ->label('Estornar Pagamento')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Estornar pagamento')
                ->modalDescription('O valor será devolvido ao cliente pelo gateway de pagamento original. Tem certeza?')
                ->visible(fn (): bool => in_array($this->record->status, ['awaiting_emission', 'completed']))
                ->action(function (): void {
                    $payment = $this->record->latestPayment;
                    if (! $payment) {
                        Notification::make()->title('Nenhum pagamento encontrado')->danger()->send();
                        return;
                    }

                    $resolver = app(PaymentGatewayResolver::class);
                    $gateway = $resolver->resolveForPayment($payment);
                    $refunded = $gateway->refundPayment($payment);

                    if ($refunded) {
                        $payment->update(['status' => 'refunded']);
                        $this->record->update(['status' => 'cancelled']);
                        Notification::make()->title('Estorno processado com sucesso')->success()->send();
                    } else {
                        Notification::make()
                            ->title('Falha ao processar estorno no gateway')
                            ->body('Verifique os logs ou faça o estorno manualmente no painel do gateway.')
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar Pedido')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar pedido')
                ->modalDescription('Tem certeza que deseja cancelar este pedido?')
                ->visible(fn (): bool => in_array($this->record->status, ['pending', 'awaiting_payment', 'awaiting_emission']))
                ->action(function (): void {
                    $this->record->update(['status' => 'cancelled']);
                    Notification::make()->title('Pedido cancelado')->danger()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
