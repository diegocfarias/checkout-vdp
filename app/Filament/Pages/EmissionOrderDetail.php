<?php

namespace App\Filament\Pages;

use App\Models\Order;
use Filament\Actions;
use Filament\Infolists;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EmissionOrderDetail extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.emission-order-detail';

    public ?int $orderId = null;

    public function mount(int|string $order): void
    {
        $orderModel = Order::with(['flights', 'flightSearch', 'passengers', 'emission.issuer'])
            ->findOrFail($order);

        $user = auth()->user();
        if (! $user->isAdmin()) {
            $emission = $orderModel->emission;
            if (! $emission || $emission->issuer_id !== $user->id) {
                abort(403);
            }
        }

        $this->orderId = $orderModel->id;
    }

    public function getOrder(): Order
    {
        return Order::with(['flights', 'flightSearch', 'passengers', 'emission'])->findOrFail($this->orderId);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->getOrder())
            ->columns(1)
            ->schema([
                Section::make('Informações do Pedido')
                    ->icon('heroicon-o-document-text')
                    ->columns(5)
                    ->schema([
                        Infolists\Components\TextEntry::make('tracking_code')
                            ->label('Código')
                            ->badge()
                            ->color('gray')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('route')
                            ->label('Rota')
                            ->getStateUsing(fn (Order $record) => $record->departure_iata && $record->arrival_iata
                                ? strtoupper($record->departure_iata) . ' → ' . strtoupper($record->arrival_iata)
                                : '-'),
                        Infolists\Components\TextEntry::make('cabin')
                            ->label('Cabine')
                            ->formatStateUsing(fn (?string $state) => match ($state) {
                                'EC' => 'Econômica',
                                'EX' => 'Executiva',
                                default => $state ? ucfirst($state) : '-',
                            }),
                        Infolists\Components\TextEntry::make('total_miles')
                            ->label('Milhas totais')
                            ->getStateUsing(function (Order $record) {
                                $record->loadMissing('flights');
                                $total = $record->flights->sum(fn ($f) => (float) ($f->price_miles ?? $f->miles_price ?? 0));

                                return $total > 0 ? number_format($total, 0, '', '.') . ' milhas' : '-';
                            })
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'awaiting_emission' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'awaiting_emission' => 'Aguardando emissão',
                                'completed' => 'Emitido',
                                'cancelled' => 'Cancelado',
                                default => ucfirst($state),
                            }),
                    ]),

                Section::make('Card para Emissão')
                    ->icon('heroicon-o-printer')
                    ->description('Tire print desta área para enviar ao milheiro')
                    ->schema([
                        Infolists\Components\ViewEntry::make('flight_cards_emission')
                            ->label('')
                            ->view('filament.components.flight-card-emission'),
                    ]),

                Section::make('Passageiros')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('passengers')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('full_name')
                                    ->label('Nome completo'),
                                Infolists\Components\TextEntry::make('nationality')
                                    ->label('Nacionalidade')
                                    ->formatStateUsing(fn (?string $state) => \App\Filament\Resources\OrderResource::nationalityLabel($state))
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('document')
                                    ->label('CPF')
                                    ->formatStateUsing(function (?string $state) {
                                        if (! $state || strlen(preg_replace('/\D/', '', $state)) !== 11) {
                                            return $state ?? '-';
                                        }
                                        $d = preg_replace('/\D/', '', $state);

                                        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
                                    })
                                    ->copyable()
                                    ->placeholder('-')
                                    ->visible(fn ($record) => ! empty($record->document)),
                                Infolists\Components\TextEntry::make('passport_number')
                                    ->label('Passaporte')
                                    ->copyable()
                                    ->placeholder('-')
                                    ->visible(fn ($record) => ! empty($record->passport_number)),
                                Infolists\Components\TextEntry::make('passport_expiry')
                                    ->label('Validade Passaporte')
                                    ->date('d/m/Y')
                                    ->placeholder('-')
                                    ->visible(fn ($record) => ! empty($record->passport_expiry)),
                                Infolists\Components\TextEntry::make('birth_date')
                                    ->label('Nascimento')
                                    ->date('d/m/Y')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('email')
                                    ->label('E-mail')
                                    ->copyable()
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('phone')
                                    ->label('Telefone')
                                    ->copyable()
                                    ->placeholder('-'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }

    public function getTitle(): string
    {
        if (! $this->orderId) {
            return 'Detalhes da Emissão';
        }

        $order = Order::find($this->orderId);

        return $order ? ('Emissão — ' . $order->tracking_code) : 'Detalhes da Emissão';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('copy_passengers')
                ->label('Copiar Passageiros')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->modalHeading('Dados dos Passageiros')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(function (): HtmlString {
                    $order = $this->getOrder();
                    $order->loadMissing('passengers');
                    $lines = [];
                    foreach ($order->passengers as $i => $p) {
                        $num = $i + 1;
                        $lines[] = "Passageiro {$num}:";
                        $lines[] = 'Nome: ' . strtoupper($p->full_name ?? '-');
                        $lines[] = 'Nacionalidade: ' . \App\Filament\Resources\OrderResource::nationalityLabel($p->nationality ?? 'BR');
                        $doc = $p->document ? preg_replace('/\D/', '', $p->document) : null;
                        if ($doc && strlen($doc) === 11) {
                            $doc = substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2);
                        }
                        if ($doc) {
                            $lines[] = 'CPF: ' . $doc;
                        }
                        if ($p->passport_number) {
                            $lines[] = 'Passaporte: ' . $p->passport_number;
                        }
                        if ($p->passport_expiry) {
                            $lines[] = 'Validade Passaporte: ' . $p->passport_expiry->format('d/m/Y');
                        }
                        $lines[] = 'Nascimento: ' . ($p->birth_date ? $p->birth_date->format('d/m/Y') : '-');
                        $lines[] = 'E-mail: ' . ($p->email ?? '-');
                        $lines[] = 'Telefone: ' . ($p->phone ?? '-');
                        $lines[] = '';
                    }
                    $text = implode("\n", $lines);
                    $escaped = e($text);

                    return new HtmlString(<<<HTML
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
                    HTML);
                }),

            Actions\Action::make('back')
                ->label('Voltar à Fila')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(route('filament.admin.pages.emission-queue')),
        ];
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/emission-order/{order}';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'emission-order';
    }
}
