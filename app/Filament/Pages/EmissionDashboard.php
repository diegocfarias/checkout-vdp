<?php

namespace App\Filament\Pages;

use App\Models\OrderEmission;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmissionDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Dashboard Emissões';

    protected static ?string $title = 'Dashboard de Emissões';

    protected static string|\UnitEnum|null $navigationGroup = 'Gestão';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.emission-dashboard';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function getStats(): array
    {
        $from = $this->dateFrom ? now()->parse($this->dateFrom)->startOfDay() : now()->startOfMonth();
        $to = $this->dateTo ? now()->parse($this->dateTo)->endOfDay() : now()->endOfDay();

        $pendingNow = OrderEmission::where('status', 'pending')->count();
        $assignedNow = OrderEmission::where('status', 'assigned')->count();

        $completedPeriod = OrderEmission::completedBetween($from, $to)->count();

        $avgDuration = OrderEmission::completedBetween($from, $to)
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        $activeIssuers = User::issuers()
            ->whereHas('emissions', fn (Builder $q) => $q->completedBetween($from, $to))
            ->count();

        return [
            [
                'label' => 'Pendentes agora',
                'value' => $pendingNow,
                'color' => 'warning',
                'icon' => 'heroicon-o-clock',
            ],
            [
                'label' => 'Em andamento',
                'value' => $assignedNow,
                'color' => 'info',
                'icon' => 'heroicon-o-play',
            ],
            [
                'label' => 'Concluídas no período',
                'value' => $completedPeriod,
                'color' => 'success',
                'icon' => 'heroicon-o-check-circle',
            ],
            [
                'label' => 'Tempo médio',
                'value' => $avgDuration ? $this->formatDuration((int) $avgDuration) : '—',
                'color' => 'primary',
                'icon' => 'heroicon-o-clock',
            ],
            [
                'label' => 'Emissores ativos',
                'value' => $activeIssuers,
                'color' => 'gray',
                'icon' => 'heroicon-o-users',
            ],
        ];
    }

    public function getRanking(): array
    {
        $from = $this->dateFrom ? now()->parse($this->dateFrom)->startOfDay() : now()->startOfMonth();
        $to = $this->dateTo ? now()->parse($this->dateTo)->endOfDay() : now()->endOfDay();

        return User::issuers()
            ->withCount(['emissions as completed_count' => fn (Builder $q) => $q->completedBetween($from, $to)])
            ->having('completed_count', '>', 0)
            ->orderByDesc('completed_count')
            ->get()
            ->map(function (User $user) use ($from, $to) {
                $avgSeconds = $user->emissions()
                    ->completedBetween($from, $to)
                    ->whereNotNull('duration_seconds')
                    ->avg('duration_seconds');

                $totalValue = $user->emissions()
                    ->completedBetween($from, $to)
                    ->sum('emission_value');

                return [
                    'name' => $user->name,
                    'count' => $user->completed_count,
                    'avg_time' => $avgSeconds ? $this->formatDuration((int) $avgSeconds) : '—',
                    'total_value' => 'R$ ' . number_format((float) $totalValue, 2, ',', '.'),
                ];
            })
            ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Emissões Recentes')
            ->query(function (): Builder {
                return OrderEmission::query()
                    ->with(['order', 'issuer'])
                    ->where('status', 'completed')
                    ->orderByDesc('completed_at');
            })
            ->columns([
                Tables\Columns\TextColumn::make('order.tracking_code')
                    ->label('Pedido')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('issuer.name')
                    ->label('Emissor'),

                Tables\Columns\TextColumn::make('duration_formatted')
                    ->label('Duração')
                    ->getStateUsing(fn (OrderEmission $record) => $record->duration_seconds
                        ? $this->formatDuration($record->duration_seconds)
                        : '—'),

                Tables\Columns\TextColumn::make('miles_cost_per_thousand')
                    ->label('Custo/milheiro')
                    ->prefix('R$ ')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('emission_value')
                    ->label('Valor')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Concluída em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->paginated([10]);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes}min {$secs}s";
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return "{$hours}h {$mins}min";
    }

    public function updatedDateFrom(): void
    {
        // Triggers Livewire re-render
    }

    public function updatedDateTo(): void
    {
        // Triggers Livewire re-render
    }
}
