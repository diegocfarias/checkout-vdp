<?php

namespace App\Filament\Pages;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderFlight;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\PaidOrdersMetricsService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaidOrdersDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Dashboard Financeiro';

    protected static ?string $title = 'Dashboard Financeiro';

    protected static string|\UnitEnum|null $navigationGroup = 'Gestão';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.paid-orders-dashboard';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $paymentMethod = 'all';

    public string $gateway = 'all';

    public string $orderStatus = 'all';

    public string $airline = 'all';

    public string $coupon = 'all';

    public string $issuerId = 'all';

    public string $deviceType = 'all';

    public ?string $departureIata = null;

    public ?string $arrivalIata = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function getDashboardData(): array
    {
        return $this->metrics()->dashboard($this->filters());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pedidos pagos recentes')
            ->query(function (): Builder {
                return $this->metrics()
                    ->paidOrdersQuery($this->filters())
                    ->with(['payments', 'flights', 'customer', 'coupon', 'emission.issuer'])
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id');
            })
            ->columns([
                Tables\Columns\TextColumn::make('tracking_code')
                    ->label('Pedido')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('route')
                    ->label('Rota')
                    ->getStateUsing(fn (Order $record): string => $this->routeLabel($record)),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Método')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (Order $record): string => $this->primaryPaymentMethodLabel($record)),

                Tables\Columns\TextColumn::make('external_revenue')
                    ->label('Receita')
                    ->getStateUsing(fn (Order $record): string => $this->money($this->orderMetric($record, 'external_revenue'))),

                Tables\Columns\TextColumn::make('gmv')
                    ->label('GMV')
                    ->getStateUsing(fn (Order $record): string => $this->money($this->orderMetric($record, 'gmv'))),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Custo')
                    ->getStateUsing(fn (Order $record): string => $this->money($this->orderMetric($record, 'total_cost'))),

                Tables\Columns\TextColumn::make('margin')
                    ->label('Margem')
                    ->getStateUsing(fn (Order $record): string => $this->money($this->orderMetric($record, 'margin'))),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $this->metrics()->orderStatusLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'awaiting_emission' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->getStateUsing(fn (Order $record): ?string => $this->paidAtLabel($record))
                    ->sortable(),
            ])
            ->paginated([10, 25, 50]);
    }

    public function resetFilters(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->paymentMethod = 'all';
        $this->gateway = 'all';
        $this->orderStatus = 'all';
        $this->airline = 'all';
        $this->coupon = 'all';
        $this->issuerId = 'all';
        $this->deviceType = 'all';
        $this->departureIata = null;
        $this->arrivalIata = null;
    }

    public function getPaymentMethodOptions(): array
    {
        $options = OrderPayment::paid()
            ->whereNotNull('payment_method')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method')
            ->filter()
            ->mapWithKeys(fn (string $method): array => [$method => $this->metrics()->paymentMethodLabel($method)])
            ->all();

        return ['all' => 'Todos'] + $options;
    }

    public function getGatewayOptions(): array
    {
        $options = OrderPayment::paid()
            ->whereNotNull('gateway')
            ->distinct()
            ->orderBy('gateway')
            ->pluck('gateway')
            ->filter()
            ->mapWithKeys(fn (string $gateway): array => [$gateway => $this->metrics()->gatewayLabel($gateway)])
            ->all();

        return ['all' => 'Todos'] + $options;
    }

    public function getStatusOptions(): array
    {
        return [
            'all' => 'Todos',
            'awaiting_emission' => 'Aguardando emissão',
            'completed' => 'Emitido',
            'cancelled' => 'Cancelado',
        ];
    }

    public function getAirlineOptions(): array
    {
        $options = OrderFlight::query()
            ->whereNotNull('cia')
            ->distinct()
            ->orderBy('cia')
            ->pluck('cia')
            ->filter()
            ->mapWithKeys(fn (string $airline): array => [$airline => strtoupper($airline)])
            ->all();

        return ['all' => 'Todas'] + $options;
    }

    public function getCouponOptions(): array
    {
        $options = Coupon::query()
            ->orderBy('code')
            ->pluck('code', 'id')
            ->mapWithKeys(fn (string $code, int $id): array => [(string) $id => strtoupper($code)])
            ->all();

        return [
            'all' => 'Todos',
            'with_coupon' => 'Com cupom',
            'without_coupon' => 'Sem cupom',
        ] + $options;
    }

    public function getIssuerOptions(): array
    {
        $options = User::issuers()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int $id): array => [(string) $id => $name])
            ->all();

        return ['all' => 'Todos'] + $options;
    }

    public function getDeviceOptions(): array
    {
        return [
            'all' => 'Todos',
            'desktop' => 'Desktop',
            'mobile' => 'Mobile',
        ];
    }

    public function money(float|int|null $value): string
    {
        return $this->metrics()->formatMoney((float) $value);
    }

    public function percent(float|int|null $value): string
    {
        return $this->metrics()->formatPercent((float) $value);
    }

    public function updatedDateFrom(): void {}

    public function updatedDateTo(): void {}

    public function updatedPaymentMethod(): void {}

    public function updatedGateway(): void {}

    public function updatedOrderStatus(): void {}

    public function updatedAirline(): void {}

    public function updatedCoupon(): void {}

    public function updatedIssuerId(): void {}

    public function updatedDeviceType(): void {}

    public function updatedDepartureIata(): void
    {
        $this->departureIata = $this->departureIata ? strtoupper($this->departureIata) : null;
    }

    public function updatedArrivalIata(): void
    {
        $this->arrivalIata = $this->arrivalIata ? strtoupper($this->arrivalIata) : null;
    }

    private function filters(): array
    {
        return [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'payment_method' => $this->paymentMethod,
            'gateway' => $this->gateway,
            'order_status' => $this->orderStatus,
            'airline' => $this->airline,
            'coupon' => $this->coupon,
            'issuer_id' => $this->issuerId,
            'device_type' => $this->deviceType,
            'departure_iata' => $this->departureIata,
            'arrival_iata' => $this->arrivalIata,
        ];
    }

    private function metrics(): PaidOrdersMetricsService
    {
        return app(PaidOrdersMetricsService::class);
    }

    private function orderMetric(Order $order, string $key): float
    {
        $metric = $this->metrics()->calculateOrderMetrics($order, $this->filters());

        return (float) ($metric[$key] ?? 0);
    }

    private function routeLabel(Order $order): string
    {
        $departure = strtoupper((string) ($order->departure_iata ?: '---'));
        $arrival = strtoupper((string) ($order->arrival_iata ?: '---'));

        return "{$departure} -> {$arrival}";
    }

    private function primaryPaymentMethodLabel(Order $order): string
    {
        $metric = $this->metrics()->calculateOrderMetrics($order, $this->filters());
        $payment = $metric['payments'][0] ?? null;

        return $this->metrics()->paymentMethodLabel($payment['method'] ?? null);
    }

    private function paidAtLabel(Order $order): ?string
    {
        $metric = $this->metrics()->calculateOrderMetrics($order, $this->filters());

        return $metric['paid_at']
            ? now()->parse($metric['paid_at'])->format('d/m/Y H:i')
            : null;
    }
}
