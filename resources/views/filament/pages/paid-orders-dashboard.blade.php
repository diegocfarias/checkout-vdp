<x-filament-panels::page>
    @php
        $dashboard = $this->getDashboardData();
        $timelineItems = $dashboard['timeline']['items'];
        $chartMax = max(1, (float) $dashboard['timeline']['max']);
        $chartCount = max(1, count($timelineItems));
        $chartLeft = 44;
        $chartTop = 18;
        $chartWidth = 552;
        $chartHeight = 152;
        $labelStep = max(1, (int) ceil($chartCount / 8));

        $pointFor = function (array $row, int $index, string $key) use ($chartCount, $chartMax, $chartLeft, $chartWidth, $chartTop, $chartHeight): string {
            $x = $chartCount > 1 ? $chartLeft + ($index * ($chartWidth / ($chartCount - 1))) : $chartLeft + ($chartWidth / 2);
            $value = max(0, (float) ($row[$key] ?? 0));
            $y = $chartTop + $chartHeight - (($value / $chartMax) * $chartHeight);

            return round($x, 2) . ',' . round($y, 2);
        };

        $gmvPoints = collect($timelineItems)->map(fn (array $row, int $index): string => $pointFor($row, $index, 'gmv'))->implode(' ');
        $revenuePoints = collect($timelineItems)->map(fn (array $row, int $index): string => $pointFor($row, $index, 'external_revenue'))->implode(' ');
        $marginPoints = collect($timelineItems)->map(fn (array $row, int $index): string => $pointFor($row, $index, 'margin'))->implode(' ');

        $inputStyle = 'width:100%; min-width:140px; padding:8px 12px; border:1px solid rgba(128,128,128,0.3); border-radius:8px; font-size:14px; background:transparent; color:inherit;';
        $labelStyle = 'display:block; margin-bottom:4px;';
    @endphp

    <div style="display:flex; flex-direction:column; gap:24px;">
        <x-filament::section :compact="true">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:14px; align-items:end;">
                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">De</label>
                    <input type="date" wire:model.live="dateFrom" style="{{ $inputStyle }}">
                </div>

                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">Até</label>
                    <input type="date" wire:model.live="dateTo" style="{{ $inputStyle }}">
                </div>

                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">Pagamento</label>
                    <select wire:model.live="paymentMethod" style="{{ $inputStyle }}">
                        @foreach($this->getPaymentMethodOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">Gateway</label>
                    <select wire:model.live="gateway" style="{{ $inputStyle }}">
                        @foreach($this->getGatewayOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">Status</label>
                    <select wire:model.live="orderStatus" style="{{ $inputStyle }}">
                        @foreach($this->getStatusOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">Companhia</label>
                    <select wire:model.live="airline" style="{{ $inputStyle }}">
                        @foreach($this->getAirlineOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">Cupom</label>
                    <select wire:model.live="coupon" style="{{ $inputStyle }}">
                        @foreach($this->getCouponOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">Emissor</label>
                    <select wire:model.live="issuerId" style="{{ $inputStyle }}">
                        @foreach($this->getIssuerOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">Dispositivo</label>
                    <select wire:model.live="deviceType" style="{{ $inputStyle }}">
                        @foreach($this->getDeviceOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">Origem</label>
                    <input type="text" maxlength="3" wire:model.live.debounce.500ms="departureIata" placeholder="GRU" style="{{ $inputStyle }} text-transform:uppercase;">
                </div>

                <div>
                    <label class="fi-section-header-description" style="{{ $labelStyle }}">Destino</label>
                    <input type="text" maxlength="3" wire:model.live.debounce.500ms="arrivalIata" placeholder="SDU" style="{{ $inputStyle }} text-transform:uppercase;">
                </div>

                <div>
                    <button type="button" wire:click="resetFilters"
                        style="width:100%; padding:9px 12px; border:1px solid rgba(128,128,128,0.3); border-radius:8px; font-size:14px; font-weight:600; background:transparent; color:inherit;">
                        Limpar filtros
                    </button>
                </div>
            </div>
        </x-filament::section>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:16px;">
            @foreach($dashboard['stats'] as $stat)
                <x-filament::section :compact="true">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px;">
                        <span class="fi-section-header-description">{{ $stat['label'] }}</span>
                        <x-filament::icon :icon="$stat['icon']" class="fi-section-header-description" style="width:20px; height:20px;" />
                    </div>
                    <div class="fi-section-header-heading" style="font-size:23px; line-height:1.15;">{{ $stat['value'] }}</div>
                    <div class="fi-section-header-description" style="margin-top:6px; font-size:12px;">{{ $stat['hint'] }}</div>
                </x-filament::section>
            @endforeach
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap:16px;">
            <x-filament::section heading="Evolução financeira">
                <div style="overflow-x:auto;">
                    <svg viewBox="0 0 640 220" width="100%" height="260" role="img" aria-label="Evolução financeira">
                        <line x1="44" y1="170" x2="596" y2="170" stroke="rgba(148,163,184,0.35)" stroke-width="1" />
                        <line x1="44" y1="94" x2="596" y2="94" stroke="rgba(148,163,184,0.18)" stroke-width="1" />
                        <line x1="44" y1="18" x2="596" y2="18" stroke="rgba(148,163,184,0.18)" stroke-width="1" />

                        @foreach($timelineItems as $index => $row)
                            @php
                                $x = $chartCount > 1 ? $chartLeft + ($index * ($chartWidth / ($chartCount - 1))) : $chartLeft + ($chartWidth / 2);
                                $barHeight = min($chartHeight, ((float) $row['gmv'] / $chartMax) * $chartHeight);
                            @endphp
                            <rect x="{{ round($x - 4, 2) }}" y="{{ round($chartTop + $chartHeight - $barHeight, 2) }}" width="8" height="{{ round($barHeight, 2) }}" rx="3" fill="rgba(14,165,233,0.18)" />
                            @if($index % $labelStep === 0 || $index === $chartCount - 1)
                                <text x="{{ round($x, 2) }}" y="204" text-anchor="middle" fill="currentColor" opacity="0.55" font-size="11">{{ $row['label'] }}</text>
                            @endif
                        @endforeach

                        <polyline points="{{ $gmvPoints }}" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <polyline points="{{ $revenuePoints }}" fill="none" stroke="#059669" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <polyline points="{{ $marginPoints }}" fill="none" stroke="#d97706" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:14px; font-size:12px;">
                    <span style="display:flex; align-items:center; gap:6px;"><span style="width:10px; height:10px; border-radius:9999px; background:#2563eb;"></span>GMV</span>
                    <span style="display:flex; align-items:center; gap:6px;"><span style="width:10px; height:10px; border-radius:9999px; background:#059669;"></span>Receita</span>
                    <span style="display:flex; align-items:center; gap:6px;"><span style="width:10px; height:10px; border-radius:9999px; background:#d97706;"></span>Margem</span>
                </div>
            </x-filament::section>

            <x-filament::section heading="Mix de pagamentos">
                <div style="display:flex; flex-direction:column; gap:14px;">
                    @forelse($dashboard['payment_methods'] as $row)
                        <div>
                            <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:6px; font-size:13px;">
                                <span style="font-weight:600;">{{ $row['label'] }}</span>
                                <span class="fi-section-header-description">{{ $this->money($row['amount']) }}</span>
                            </div>
                            <div style="height:9px; border-radius:9999px; background:rgba(148,163,184,0.18); overflow:hidden;">
                                <div style="height:100%; width:{{ max(2, $row['share']) }}%; border-radius:9999px; background:#2563eb;"></div>
                            </div>
                        </div>
                    @empty
                        <div class="fi-section-header-description">Nenhum pagamento pago no período.</div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap:16px;">
            <x-filament::section heading="Companhias">
                <div style="display:flex; flex-direction:column; gap:12px;">
                    @forelse($dashboard['airlines'] as $row)
                        <div>
                            <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:5px; font-size:13px;">
                                <span style="font-weight:600;">{{ $row['label'] }}</span>
                                <span class="fi-section-header-description">{{ $this->money($row['amount']) }} · {{ $row['flights'] }} voos</span>
                            </div>
                            <div style="height:8px; border-radius:9999px; background:rgba(148,163,184,0.18); overflow:hidden;">
                                <div style="height:100%; width:{{ max(2, $row['share']) }}%; border-radius:9999px; background:#059669;"></div>
                            </div>
                        </div>
                    @empty
                        <div class="fi-section-header-description">Sem voos pagos no período.</div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Gateways">
                <div style="display:flex; flex-direction:column; gap:12px;">
                    @forelse($dashboard['gateways'] as $row)
                        <div>
                            <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:5px; font-size:13px;">
                                <span style="font-weight:600;">{{ $row['label'] }}</span>
                                <span class="fi-section-header-description">{{ $this->money($row['amount']) }}</span>
                            </div>
                            <div style="height:8px; border-radius:9999px; background:rgba(148,163,184,0.18); overflow:hidden;">
                                <div style="height:100%; width:{{ max(2, $row['share']) }}%; border-radius:9999px; background:#7c3aed;"></div>
                            </div>
                        </div>
                    @empty
                        <div class="fi-section-header-description">Sem gateways no período.</div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Status pós-pagamento">
                <div style="display:flex; flex-direction:column; gap:12px;">
                    @forelse($dashboard['statuses'] as $row)
                        <div>
                            <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:5px; font-size:13px;">
                                <span style="font-weight:600;">{{ $row['label'] }}</span>
                                <span class="fi-section-header-description">{{ $row['orders'] }} pedidos</span>
                            </div>
                            <div style="height:8px; border-radius:9999px; background:rgba(148,163,184,0.18); overflow:hidden;">
                                <div style="height:100%; width:{{ max(2, $row['share']) }}%; border-radius:9999px; background:#d97706;"></div>
                            </div>
                        </div>
                    @empty
                        <div class="fi-section-header-description">Sem pedidos no período.</div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 360px), 1fr)); gap:16px;">
            <x-filament::section heading="Top rotas">
                <div style="overflow-x:auto;">
                    <table style="width:100%; font-size:14px; border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid rgba(128,128,128,0.2);">
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:left;">Rota</th>
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:right;">Pedidos</th>
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:right;">GMV</th>
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:right;">Margem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dashboard['routes'] as $row)
                                <tr style="border-top:1px solid rgba(128,128,128,0.1);">
                                    <td style="padding:10px 12px; font-weight:600;">{{ $row['label'] }}</td>
                                    <td style="padding:10px 12px; text-align:right;">{{ $row['orders'] }}</td>
                                    <td style="padding:10px 12px; text-align:right;">{{ $this->money($row['amount']) }}</td>
                                    <td style="padding:10px 12px; text-align:right;">{{ $this->money($row['margin']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="fi-section-header-description" style="padding:12px;">Nenhuma rota no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section heading="Cupons">
                <div style="overflow-x:auto;">
                    <table style="width:100%; font-size:14px; border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid rgba(128,128,128,0.2);">
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:left;">Cupom</th>
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:right;">Pedidos</th>
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:right;">Desconto</th>
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:right;">GMV</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dashboard['coupons'] as $row)
                                <tr style="border-top:1px solid rgba(128,128,128,0.1);">
                                    <td style="padding:10px 12px; font-weight:600;">{{ $row['label'] }}</td>
                                    <td style="padding:10px 12px; text-align:right;">{{ $row['orders'] }}</td>
                                    <td style="padding:10px 12px; text-align:right;">{{ $this->money($row['discount']) }}</td>
                                    <td style="padding:10px 12px; text-align:right;">{{ $this->money($row['amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="fi-section-header-description" style="padding:12px;">Nenhum cupom no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section heading="Emissores">
                <div style="overflow-x:auto;">
                    <table style="width:100%; font-size:14px; border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid rgba(128,128,128,0.2);">
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:left;">Emissor</th>
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:right;">Pedidos</th>
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:right;">Custo</th>
                                <th class="fi-section-header-description" style="padding:10px 12px; text-align:right;">Margem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dashboard['issuers'] as $row)
                                <tr style="border-top:1px solid rgba(128,128,128,0.1);">
                                    <td style="padding:10px 12px; font-weight:600;">{{ $row['label'] }}</td>
                                    <td style="padding:10px 12px; text-align:right;">{{ $row['orders'] }}</td>
                                    <td style="padding:10px 12px; text-align:right;">{{ $this->money($row['cost']) }}</td>
                                    <td style="padding:10px 12px; text-align:right;">{{ $this->money($row['margin']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="fi-section-header-description" style="padding:12px;">Nenhum emissor no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
