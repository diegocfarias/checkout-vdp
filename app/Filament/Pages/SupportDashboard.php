<?php

namespace App\Filament\Pages;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Dashboard Atendimento';

    protected static ?string $title = 'Dashboard de Atendimento';

    protected static string|\UnitEnum|null $navigationGroup = 'Atendimento';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.support-dashboard';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function getStats(): array
    {
        $from = $this->dateFrom ? now()->parse($this->dateFrom)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $this->dateTo ? now()->parse($this->dateTo)->endOfDay() : now()->endOfDay();

        $openNow = SupportTicket::open()->count();
        $awaitingResponse = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();

        $totalPeriod = SupportTicket::whereBetween('created_at', [$from, $to])->count();
        $resolvedPeriod = SupportTicket::whereIn('status', ['resolved', 'closed'])
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $avgFirstResponse = SupportTicket::whereNotNull('first_response_at')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_minutes')
            ->value('avg_minutes');

        $avgResolution = SupportTicket::whereNotNull('resolved_at')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg_minutes')
            ->value('avg_minutes');

        $resolutionRate = $totalPeriod > 0 ? round(($resolvedPeriod / $totalPeriod) * 100) : 0;

        return [
            [
                'label' => 'Abertos agora',
                'value' => $openNow,
                'color' => 'danger',
                'icon' => 'heroicon-o-inbox',
            ],
            [
                'label' => 'Aguardando resposta',
                'value' => $awaitingResponse,
                'color' => 'warning',
                'icon' => 'heroicon-o-clock',
            ],
            [
                'label' => 'Total no período',
                'value' => $totalPeriod,
                'color' => 'info',
                'icon' => 'heroicon-o-ticket',
            ],
            [
                'label' => 'Tempo médio 1ª resposta',
                'value' => $avgFirstResponse ? $this->formatMinutes((int) $avgFirstResponse) : '—',
                'color' => 'primary',
                'icon' => 'heroicon-o-bolt',
            ],
            [
                'label' => 'Tempo médio resolução',
                'value' => $avgResolution ? $this->formatMinutes((int) $avgResolution) : '—',
                'color' => 'primary',
                'icon' => 'heroicon-o-check-circle',
            ],
            [
                'label' => 'Taxa de resolução',
                'value' => $resolutionRate . '%',
                'color' => 'success',
                'icon' => 'heroicon-o-chart-pie',
            ],
        ];
    }

    public function getRanking(): array
    {
        $from = $this->dateFrom ? now()->parse($this->dateFrom)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $this->dateTo ? now()->parse($this->dateTo)->endOfDay() : now()->endOfDay();

        return User::whereIn('role', ['admin', 'support'])
            ->where('is_active', true)
            ->get()
            ->map(function (User $user) use ($from, $to) {
                $assigned = SupportTicket::where('assigned_to', $user->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->count();

                $resolved = SupportTicket::where('assigned_to', $user->id)
                    ->whereIn('status', ['resolved', 'closed'])
                    ->whereBetween('created_at', [$from, $to])
                    ->count();

                $openCount = SupportTicket::where('assigned_to', $user->id)
                    ->open()
                    ->count();

                $avgMinutes = SupportTicketMessage::whereHas('ticket', function ($q) use ($user, $from, $to) {
                    $q->where('assigned_to', $user->id)->whereBetween('created_at', [$from, $to]);
                })->where('user_id', $user->id)
                    ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, (SELECT st.created_at FROM support_tickets st WHERE st.id = support_ticket_messages.support_ticket_id), support_ticket_messages.created_at)) as avg_min')
                    ->value('avg_min');

                return [
                    'name' => $user->name,
                    'assigned' => $assigned,
                    'resolved' => $resolved,
                    'open' => $openCount,
                    'avg_response' => $avgMinutes ? $this->formatMinutes((int) $avgMinutes) : '—',
                ];
            })
            ->filter(fn ($row) => $row['assigned'] > 0 || $row['resolved'] > 0 || $row['open'] > 0)
            ->sortByDesc('resolved')
            ->values()
            ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Tickets Recentes')
            ->query(function (): Builder {
                return SupportTicket::query()
                    ->with(['customer', 'agent', 'order'])
                    ->orderByDesc('created_at');
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#'),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Assunto')
                    ->formatStateUsing(fn (string $state) => SupportTicket::SUBJECTS[$state] ?? $state)
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente'),

                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Atendente')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state) => SupportTicket::STATUSES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'awaiting_customer' => 'info',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->paginated([10]);
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes}min";
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours < 24) {
            return "{$hours}h {$mins}min";
        }

        $days = intdiv($hours, 24);
        $remainHours = $hours % 24;

        return "{$days}d {$remainHours}h";
    }

    public function updatedDateFrom(): void {}

    public function updatedDateTo(): void {}
}
