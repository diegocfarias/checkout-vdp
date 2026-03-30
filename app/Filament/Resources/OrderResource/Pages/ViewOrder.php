<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\PaymentGatewayResolver;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('copy_passengers')
                ->label('Copiar Passageiros')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->visible(fn (): bool => $this->record->passengers->isNotEmpty())
                ->modalHeading('Dados dos Passageiros')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(function (): HtmlString {
                    $this->record->loadMissing('passengers');
                    $lines = [];
                    foreach ($this->record->passengers as $i => $p) {
                        $num = $i + 1;
                        $lines[] = "Passageiro {$num}:";
                        $lines[] = "Nome: " . strtoupper($p->full_name ?? '-');
                        $lines[] = "Nacionalidade: " . OrderResource::nationalityLabel($p->nationality ?? 'BR');
                        $doc = $p->document ? preg_replace('/\D/', '', $p->document) : null;
                        if ($doc && strlen($doc) === 11) {
                            $doc = substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2);
                        }
                        if ($doc) {
                            $lines[] = "CPF: " . $doc;
                        }
                        if ($p->passport_number) {
                            $lines[] = "Passaporte: " . $p->passport_number;
                        }
                        if ($p->passport_expiry) {
                            $lines[] = "Validade Passaporte: " . $p->passport_expiry->format('d/m/Y');
                        }
                        $lines[] = "Nascimento: " . ($p->birth_date ? $p->birth_date->format('d/m/Y') : '-');
                        $lines[] = "E-mail: " . ($p->email ?? '-');
                        $lines[] = "Telefone: " . ($p->phone ?? '-');
                        $lines[] = "";
                    }
                    $text = implode("\n", $lines);
                    $escaped = e($text);
                    $html = <<<HTML
                    <div>
                        <pre id="passengers-text" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px;font-size:13px;line-height:1.6;white-space:pre-wrap;font-family:'Inter',monospace;color:#374151;max-height:400px;overflow-y:auto;">{$escaped}</pre>
                        <button type="button" onclick="
                            var text = document.getElementById('passengers-text').innerText;
                            navigator.clipboard.writeText(text).then(function() {
                                var btn = event.target;
                                btn.innerText = 'Copiado!';
                                btn.style.background = '#059669';
                                setTimeout(function() { btn.innerText = 'Copiar dados'; btn.style.background = '#2563eb'; }, 2000);
                            });
                        " style="margin-top:12px;background:#2563eb;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;width:100%;">
                            Copiar dados
                        </button>
                    </div>
                    HTML;

                    return new HtmlString($html);
                }),

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
                ->visible(fn (): bool => $this->record->status === 'awaiting_payment')
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
