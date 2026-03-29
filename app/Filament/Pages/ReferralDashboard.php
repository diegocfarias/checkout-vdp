<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Referral;
use App\Models\WalletTransaction;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReferralDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Dashboard Indicações';

    protected static ?string $title = 'Dashboard de Indicações';

    protected static string|\UnitEnum|null $navigationGroup = 'Gestão';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.pages.referral-dashboard';

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

        $activeAffiliates = Customer::where('is_affiliate', true)->count();

        $referralsQuery = Referral::where('status', 'active')
            ->whereBetween('created_at', [$from, $to]);

        $totalReferrals = (clone $referralsQuery)->count();
        $gmv = (clone $referralsQuery)->sum('order_base_total');
        $totalDiscount = (clone $referralsQuery)->sum('discount_amount');
        $totalCreditGenerated = (clone $referralsQuery)->sum('credit_amount');

        $totalCreditReleased = WalletTransaction::where('type', 'credit')
            ->whereNotNull('referral_id')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $totalCreditUsed = WalletTransaction::where('type', 'debit')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $pendingBalance = Referral::where('credit_status', 'pending')
            ->where('status', 'active')
            ->sum('credit_amount');

        return [
            ['label' => 'Afiliados ativos', 'value' => $activeAffiliates, 'icon' => 'heroicon-o-users'],
            ['label' => 'Indicações no período', 'value' => $totalReferrals, 'icon' => 'heroicon-o-gift'],
            ['label' => 'GMV por indicações', 'value' => 'R$ ' . number_format((float) $gmv, 2, ',', '.'), 'icon' => 'heroicon-o-banknotes'],
            ['label' => 'Desconto concedido', 'value' => 'R$ ' . number_format((float) $totalDiscount, 2, ',', '.'), 'icon' => 'heroicon-o-receipt-percent'],
            ['label' => 'Crédito gerado', 'value' => 'R$ ' . number_format((float) $totalCreditGenerated, 2, ',', '.'), 'icon' => 'heroicon-o-arrow-trending-up'],
            ['label' => 'Crédito liberado', 'value' => 'R$ ' . number_format((float) $totalCreditReleased, 2, ',', '.'), 'icon' => 'heroicon-o-check-circle'],
            ['label' => 'Crédito usado', 'value' => 'R$ ' . number_format((float) $totalCreditUsed, 2, ',', '.'), 'icon' => 'heroicon-o-shopping-cart'],
            ['label' => 'Saldo pendente', 'value' => 'R$ ' . number_format((float) $pendingBalance, 2, ',', '.'), 'icon' => 'heroicon-o-clock'],
        ];
    }

    public function getRanking(): array
    {
        $from = $this->dateFrom ? now()->parse($this->dateFrom)->startOfDay() : now()->startOfMonth();
        $to = $this->dateTo ? now()->parse($this->dateTo)->endOfDay() : now()->endOfDay();

        return Customer::where('is_affiliate', true)
            ->withCount(['referrals as referrals_count' => fn (Builder $q) => $q->where('status', 'active')->whereBetween('created_at', [$from, $to])])
            ->having('referrals_count', '>', 0)
            ->orderByDesc('referrals_count')
            ->limit(10)
            ->get()
            ->map(function (Customer $customer) use ($from, $to) {
                $gmv = $customer->referrals()
                    ->where('status', 'active')
                    ->whereBetween('created_at', [$from, $to])
                    ->sum('order_base_total');

                $credits = $customer->referrals()
                    ->where('status', 'active')
                    ->whereBetween('created_at', [$from, $to])
                    ->sum('credit_amount');

                return [
                    'name' => $customer->name,
                    'referral_code' => $customer->referral_code,
                    'count' => $customer->referrals_count,
                    'gmv' => 'R$ ' . number_format((float) $gmv, 2, ',', '.'),
                    'credits' => 'R$ ' . number_format((float) $credits, 2, ',', '.'),
                ];
            })
            ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Indicações Recentes')
            ->query(function (): Builder {
                return Referral::query()
                    ->with(['affiliate', 'referredOrder'])
                    ->orderByDesc('created_at');
            })
            ->columns([
                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Afiliado'),
                Tables\Columns\TextColumn::make('referredOrder.tracking_code')
                    ->label('Pedido')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Desconto')
                    ->money('BRL'),
                Tables\Columns\TextColumn::make('credit_amount')
                    ->label('Crédito')
                    ->money('BRL'),
                Tables\Columns\TextColumn::make('credit_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'available' => 'success',
                        'used' => 'info',
                        'reversed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'available' => 'Disponível',
                        'used' => 'Usado',
                        'reversed' => 'Revertido',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->paginated([10]);
    }

    public function updatedDateFrom(): void {}

    public function updatedDateTo(): void {}
}
