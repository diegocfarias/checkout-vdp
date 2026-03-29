<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShowcaseRefreshLogResource\Pages;
use App\Models\ShowcaseRefreshLog;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShowcaseRefreshLogResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected static ?string $model = ShowcaseRefreshLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Histórico Vitrine';

    protected static ?string $modelLabel = 'Log de Atualização';

    protected static ?string $pluralModelLabel = 'Histórico de Atualizações';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationParentItem = 'Vitrine';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('showcaseRoute.arrival_city')
                    ->label('Destino')
                    ->description(fn (ShowcaseRefreshLog $record) => $record->showcaseRoute?->routeLabel())
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'running' => 'Em andamento',
                        'completed' => 'Concluído',
                        'failed' => 'Falhou',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'running' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('dates_searched')
                    ->label('Datas')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('cache_hits')
                    ->label('Cache')
                    ->alignCenter()
                    ->color('info')
                    ->description(fn (ShowcaseRefreshLog $record) => $record->dates_searched > 0
                        ? round($record->cache_hits / $record->dates_searched * 100) . '% hit'
                        : null),

                Tables\Columns\TextColumn::make('api_calls')
                    ->label('API')
                    ->alignCenter()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('errors_count')
                    ->label('Erros')
                    ->alignCenter()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('best_price')
                    ->label('Melhor preço')
                    ->formatStateUsing(fn (?string $state) => $state ? 'R$ ' . number_format((float) $state, 2, ',', '.') : '—')
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('previous_price')
                    ->label('Preço anterior')
                    ->formatStateUsing(fn (?string $state) => $state ? 'R$ ' . number_format((float) $state, 2, ',', '.') : '—')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('best_date')
                    ->label('Data mais barata')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('duration_seconds')
                    ->label('Duração')
                    ->formatStateUsing(function (?int $state) {
                        if (! $state) {
                            return '—';
                        }
                        if ($state < 60) {
                            return $state . 's';
                        }

                        return floor($state / 60) . 'min ' . ($state % 60) . 's';
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'running' => 'Em andamento',
                        'completed' => 'Concluído',
                        'failed' => 'Falhou',
                    ]),

                SelectFilter::make('showcase_route_id')
                    ->label('Rota')
                    ->relationship('showcaseRoute', 'arrival_city'),
            ])
            ->actions([
                Actions\Action::make('view_error')
                    ->label('Ver erro')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->modalHeading('Detalhes do erro')
                    ->modalContent(fn (ShowcaseRefreshLog $record) => view('filament.components.showcase-error-detail', ['log' => $record]))
                    ->visible(fn (ShowcaseRefreshLog $record) => ! empty($record->error_message)),
            ])
            ->poll('10s');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShowcaseRefreshLogs::route('/'),
        ];
    }
}
