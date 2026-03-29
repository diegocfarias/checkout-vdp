<?php

namespace App\Filament\Pages;

use App\Models\Order;
use Filament\Pages\Page;
use Filament\Panel;

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

    public function getOrderData(): ?array
    {
        if (! $this->orderId) {
            return null;
        }

        $order = Order::with(['flights', 'flightSearch', 'passengers', 'emission'])->find($this->orderId);
        if (! $order) {
            return null;
        }

        $cabin = match ($order->cabin) {
            'EC' => 'Econômica',
            'EX' => 'Executiva',
            default => $order->cabin ? ucfirst($order->cabin) : '-',
        };

        $flights = [];
        foreach ($order->flights as $flight) {
            $conns = is_array($flight->connection) ? $flight->connection : [];
            $stops = count($conns) > 1 ? count($conns) - 1 : 0;

            $flightDate = null;
            if ($order->flightSearch) {
                $dt = $flight->direction === 'outbound'
                    ? $order->flightSearch->outbound_date
                    : $order->flightSearch->inbound_date;
                $flightDate = $dt ? $dt->format('d/m/Y') . ' (' . $dt->translatedFormat('l') . ')' : null;
            }

            $flights[] = [
                'direction' => $flight->direction,
                'dir_label' => $flight->direction === 'outbound' ? 'IDA' : 'VOLTA',
                'dir_color' => $flight->direction === 'outbound' ? '#2563eb' : '#7c3aed',
                'date' => $flightDate,
                'cia' => strtoupper(trim($flight->cia ?? '')),
                'flight_number' => $flight->flight_number ?? '',
                'departure_location' => $flight->departure_location ?? '',
                'departure_time' => $flight->departure_time ?? '--:--',
                'departure_label' => $flight->departure_label ?? '',
                'arrival_location' => $flight->arrival_location ?? '',
                'arrival_time' => $flight->arrival_time ?? '--:--',
                'arrival_label' => $flight->arrival_label ?? '',
                'duration' => $flight->total_flight_duration ?? '',
                'stops' => $stops,
                'stops_label' => $stops === 0 ? 'Direto' : $stops . ' conexão' . ($stops > 1 ? 'es' : ''),
                'miles' => (float) ($flight->price_miles ?? $flight->miles_price ?? 0),
                'connections' => $conns,
            ];
        }

        $passengers = [];
        foreach ($order->passengers as $p) {
            $doc = $p->document ? preg_replace('/\D/', '', $p->document) : null;
            if ($doc && strlen($doc) === 11) {
                $doc = substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2);
            }

            $passengers[] = [
                'name' => strtoupper($p->full_name ?? '-'),
                'cpf' => $doc ?? '-',
                'birth_date' => $p->birth_date ? $p->birth_date->format('d/m/Y') : '-',
                'email' => $p->email ?? '-',
                'phone' => $p->phone ?? '-',
            ];
        }

        $totalMiles = array_sum(array_column($flights, 'miles'));

        $statusLabel = match ($order->status) {
            'awaiting_emission' => 'Aguardando emissão',
            'completed' => 'Emitido',
            'cancelled' => 'Cancelado',
            default => ucfirst($order->status),
        };

        return [
            'tracking_code' => $order->tracking_code,
            'route' => strtoupper($order->departure_iata) . ' → ' . strtoupper($order->arrival_iata),
            'cabin' => $cabin,
            'status' => $order->status,
            'status_label' => $statusLabel,
            'total_miles' => $totalMiles,
            'flights' => $flights,
            'passengers' => $passengers,
        ];
    }

    public function getTitle(): string
    {
        if (! $this->orderId) {
            return 'Detalhes da Emissão';
        }

        $order = Order::find($this->orderId);

        return $order ? ('Emissão — ' . $order->tracking_code) : 'Detalhes da Emissão';
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
