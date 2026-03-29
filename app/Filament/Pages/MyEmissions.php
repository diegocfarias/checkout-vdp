<?php

namespace App\Filament\Pages;

use App\Models\OrderEmission;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyEmissions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Minhas Emissões';

    protected static ?string $title = 'Minhas Emissões';

    protected static string|\UnitEnum|null $navigationGroup = 'Operacional';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.my-emissions';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function getStats(): array
    {
        $userId = auth()->id();
        $from = $this->dateFrom ? now()->parse($this->dateFrom)->startOfDay() : now()->startOfMonth();
        $to = $this->dateTo ? now()->parse($this->dateTo)->endOfDay() : now()->endOfDay();

        $total = OrderEmission::forIssuer($userId)
            ->completedBetween($from, $to)
            ->count();

        $avgDuration = OrderEmission::forIssuer($userId)
            ->completedBetween($from, $to)
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        $totalValue = OrderEmission::forIssuer($userId)
            ->completedBetween($from, $to)
            ->sum('emission_value');

        $assignedNow = OrderEmission::forIssuer($userId)
            ->where('status', 'assigned')
            ->count();

        return [
            [
                'label' => 'Emissões no período',
                'value' => $total,
                'icon' => 'heroicon-o-check-circle',
            ],
            [
                'label' => 'Tempo médio',
                'value' => $avgDuration ? $this->formatDuration((int) $avgDuration) : '—',
                'icon' => 'heroicon-o-clock',
            ],
            [
                'label' => 'A receber',
                'value' => 'R$ ' . number_format((float) $totalValue, 2, ',', '.'),
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => 'Em andamento',
                'value' => $assignedNow,
                'icon' => 'heroicon-o-play',
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Histórico de Emissões')
            ->query(function (): Builder {
                return OrderEmission::query()
                    ->with(['order'])
                    ->where('issuer_id', auth()->id())
                    ->where('status', 'completed')
                    ->when($this->dateFrom, fn (Builder $q) => $q->where('completed_at', '>=', now()->parse($this->dateFrom)->startOfDay()))
                    ->when($this->dateTo, fn (Builder $q) => $q->where('completed_at', '<=', now()->parse($this->dateTo)->endOfDay()))
                    ->orderByDesc('completed_at');
            })
            ->columns([
                Tables\Columns\TextColumn::make('order.tracking_code')
                    ->label('Pedido')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('route')
                    ->label('Rota')
                    ->getStateUsing(fn (OrderEmission $record) => $record->order
                        ? strtoupper($record->order->departure_iata) . ' → ' . strtoupper($record->order->arrival_iata)
                        : '-'),

                Tables\Columns\TextColumn::make('duration_formatted')
                    ->label('Duração')
                    ->getStateUsing(fn (OrderEmission $record) => $record->duration_seconds
                        ? $this->formatDuration($record->duration_seconds)
                        : '—'),

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
    }

    public function updatedDateTo(): void
    {
    }
}
