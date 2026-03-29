<?php

namespace App\Filament\Pages;

use App\Jobs\NotifyIssuersNewEmission;
use App\Models\OrderEmission;
use App\Models\OrderEmissionLog;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EmissionQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Fila de Emissões';

    protected static ?string $title = 'Fila de Emissões';

    protected static string|\UnitEnum|null $navigationGroup = 'Operacional';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.emission-queue';

    protected ?string $pollingInterval = '15s';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        return $table
            ->query(function () use ($user, $isAdmin): Builder {
                $query = OrderEmission::query()
                    ->with(['order.passengers', 'order.flights', 'order.flightSearch', 'issuer'])
                    ->whereIn('status', ['pending', 'assigned']);

                if (! $isAdmin) {
                    $query->where(function (Builder $q) use ($user) {
                        $q->where('status', 'pending')
                            ->orWhere(function (Builder $q2) use ($user) {
                                $q2->where('status', 'assigned')
                                    ->where('issuer_id', $user->id);
                            });
                    });
                }

                return $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                    ->orderBy('created_at');
            })
            ->columns([
                Tables\Columns\TextColumn::make('order.tracking_code')
                    ->label('Código')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('route')
                    ->label('Rota')
                    ->getStateUsing(fn (OrderEmission $record) => $record->order
                        ? strtoupper($record->order->departure_iata) . ' → ' . strtoupper($record->order->arrival_iata)
                        : '-'),

                Tables\Columns\TextColumn::make('passenger')
                    ->label('Passageiro')
                    ->getStateUsing(fn (OrderEmission $record) => $record->order?->passengers->first()?->full_name ?? '-'),

                Tables\Columns\TextColumn::make('flight_date')
                    ->label('Data Voo')
                    ->getStateUsing(function (OrderEmission $record) {
                        $search = $record->order?->flightSearch;
                        if (! $search || ! $search->outbound_date) {
                            return '-';
                        }
                        return $search->outbound_date->format('d/m/Y');
                    }),

                Tables\Columns\TextColumn::make('miles')
                    ->label('Milhas')
                    ->getStateUsing(function (OrderEmission $record) {
                        $total = $record->order?->flights->sum(fn ($f) => (float) ($f->price_miles ?? $f->miles_price ?? 0));
                        return $total > 0 ? number_format($total, 0, '', '.') : '-';
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Pendente',
                        'assigned' => 'Atribuída',
                        'completed' => 'Concluída',
                        'cancelled' => 'Cancelada',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'assigned' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('issuer.name')
                    ->label('Emissor')
                    ->placeholder('—')
                    ->visible(fn () => auth()->user()->isAdmin()),

                Tables\Columns\TextColumn::make('queue_time')
                    ->label('Na fila')
                    ->getStateUsing(function (OrderEmission $record) {
                        $from = $record->created_at;
                        if (! $from) {
                            return '-';
                        }
                        $diff = $from->diffForHumans(short: true);
                        return $diff;
                    }),
            ])
            ->actions([
                Actions\Action::make('claim')
                    ->label('Assumir')
                    ->icon('heroicon-o-hand-raised')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Assumir emissão')
                    ->modalDescription('Tem certeza que deseja assumir esta emissão?')
                    ->visible(fn (OrderEmission $record) => $record->status === 'pending')
                    ->action(function (OrderEmission $record): void {
                        $this->claimEmission($record);
                    }),

                Actions\Action::make('release')
                    ->label('Devolver')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Devolver emissão')
                    ->modalDescription('A emissão voltará para a fila e outros emissores serão notificados.')
                    ->visible(fn (OrderEmission $record) => $record->status === 'assigned' && $record->issuer_id === auth()->id())
                    ->action(function (OrderEmission $record): void {
                        $this->releaseEmission($record);
                    }),

                Actions\Action::make('reassign')
                    ->label('Reatribuir')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (OrderEmission $record) => auth()->user()->isAdmin() && $record->status === 'assigned')
                    ->form([
                        Select::make('new_issuer_id')
                            ->label('Novo emissor')
                            ->options(fn () => User::issuers()->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (OrderEmission $record, array $data): void {
                        $this->reassignEmission($record, (int) $data['new_issuer_id']);
                    }),

                Actions\Action::make('complete')
                    ->label('Emitir')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (OrderEmission $record) => $record->status === 'assigned' && $record->issuer_id === auth()->id())
                    ->form([
                        TextInput::make('loc')
                            ->label('Localizador (LOC)')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('Ex: ABC123'),

                        TextInput::make('miles_cost_per_thousand')
                            ->label('Valor do milheiro (R$)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->prefix('R$')
                            ->placeholder('Ex: 25.00'),
                    ])
                    ->action(function (OrderEmission $record, array $data): void {
                        $this->completeEmission($record, $data['loc'], (float) $data['miles_cost_per_thousand']);
                    }),

                Actions\Action::make('view_order')
                    ->label('Ver pedido')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn () => auth()->user()->isAdmin())
                    ->url(fn (OrderEmission $record) => route('filament.admin.resources.orders.view', $record->order_id)),
            ])
            ->poll('15s')
            ->emptyStateHeading('Nenhuma emissão na fila')
            ->emptyStateDescription('Quando um pagamento for confirmado, a emissão aparecerá aqui.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    private function claimEmission(OrderEmission $emission): void
    {
        try {
            DB::transaction(function () use ($emission) {
                $locked = OrderEmission::where('id', $emission->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new \Exception('Esta emissão já foi assumida por outro emissor.');
                }

                $locked->update([
                    'status' => 'assigned',
                    'issuer_id' => auth()->id(),
                    'assigned_at' => now(),
                ]);

                OrderEmissionLog::create([
                    'order_emission_id' => $locked->id,
                    'action' => 'assigned',
                    'user_id' => auth()->id(),
                    'to_issuer_id' => auth()->id(),
                ]);
            });

            Notification::make()
                ->title('Emissão assumida com sucesso!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Não foi possível assumir')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function releaseEmission(OrderEmission $emission): void
    {
        $fromIssuerId = $emission->issuer_id;

        $emission->update([
            'status' => 'pending',
            'issuer_id' => null,
            'assigned_at' => null,
        ]);

        OrderEmissionLog::create([
            'order_emission_id' => $emission->id,
            'action' => 'released',
            'user_id' => auth()->id(),
            'from_issuer_id' => $fromIssuerId,
        ]);

        NotifyIssuersNewEmission::dispatch($emission->order);

        Notification::make()
            ->title('Emissão devolvida para a fila')
            ->body('Os emissores foram notificados.')
            ->success()
            ->send();
    }

    private function reassignEmission(OrderEmission $emission, int $newIssuerId): void
    {
        $fromIssuerId = $emission->issuer_id;

        $emission->update([
            'issuer_id' => $newIssuerId,
            'assigned_at' => now(),
        ]);

        OrderEmissionLog::create([
            'order_emission_id' => $emission->id,
            'action' => 'reassigned',
            'user_id' => auth()->id(),
            'from_issuer_id' => $fromIssuerId,
            'to_issuer_id' => $newIssuerId,
        ]);

        Notification::make()
            ->title('Emissão reatribuída com sucesso!')
            ->success()
            ->send();
    }

    private function completeEmission(OrderEmission $emission, string $loc, float $milesCost): void
    {
        $now = now();
        $duration = $emission->calculateDuration();
        $emissionValue = (float) Setting::get('emission_value_per_order', 0);

        $emission->update([
            'status' => 'completed',
            'completed_at' => $now,
            'duration_seconds' => $duration ?? $emission->assigned_at?->diffInSeconds($now),
            'emission_value' => $emissionValue,
            'miles_cost_per_thousand' => $milesCost,
        ]);

        $emission->order->update([
            'status' => 'completed',
            'loc' => $loc,
        ]);

        OrderEmissionLog::create([
            'order_emission_id' => $emission->id,
            'action' => 'completed',
            'user_id' => auth()->id(),
            'notes' => "LOC: {$loc}",
        ]);

        Notification::make()
            ->title('Emissão concluída!')
            ->body("Localizador: {$loc}")
            ->success()
            ->send();
    }
}
