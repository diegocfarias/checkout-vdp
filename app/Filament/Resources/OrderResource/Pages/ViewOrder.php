<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\PaymentGatewayResolver;
use Filament\Actions;
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
                ->requiresConfirmation()
                ->modalHeading('Confirmar emissão')
                ->modalDescription('Marcar este pedido como emitido? O cliente será notificado.')
                ->visible(fn (): bool => $this->record->status === 'awaiting_emission')
                ->action(function (): void {
                    $this->record->update(['status' => 'completed']);
                    Notification::make()->title('Pedido marcado como emitido')->success()->send();
                    $this->refreshFormData(['status']);
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
