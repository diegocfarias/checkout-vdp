<x-filament-panels::page>
    <div style="display:flex; flex-direction:column; gap:24px;">

        {{-- Filtro de período --}}
        <x-filament::section :compact="true">
            <div style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:16px;">
                <div>
                    <label class="fi-section-header-description" style="display:block; margin-bottom:4px;">De</label>
                    <input type="date" wire:model.live="dateFrom"
                        style="padding:8px 12px; border:1px solid rgba(128,128,128,0.3); border-radius:8px; font-size:14px; background:transparent; color:inherit;">
                </div>
                <div>
                    <label class="fi-section-header-description" style="display:block; margin-bottom:4px;">Até</label>
                    <input type="date" wire:model.live="dateTo"
                        style="padding:8px 12px; border:1px solid rgba(128,128,128,0.3); border-radius:8px; font-size:14px; background:transparent; color:inherit;">
                </div>
            </div>
        </x-filament::section>

        {{-- Stats --}}
        <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:16px;">
            @foreach($this->getStats() as $stat)
                <x-filament::section :compact="true">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                        <x-filament::icon :icon="$stat['icon']" class="fi-section-header-description" style="width:20px; height:20px;" />
                        <span class="fi-section-header-description">{{ $stat['label'] }}</span>
                    </div>
                    <div class="fi-section-header-heading" style="font-size:24px;">{{ $stat['value'] }}</div>
                </x-filament::section>
            @endforeach
        </div>

        {{-- Ranking --}}
        @php $ranking = $this->getRanking(); @endphp
        @if(count($ranking) > 0)
            <x-filament::section heading="Ranking de Emissores">
                <table style="width:100%; font-size:14px; border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:1px solid rgba(128,128,128,0.2);">
                            <th class="fi-section-header-description" style="padding:10px 16px; text-align:left;">#</th>
                            <th class="fi-section-header-description" style="padding:10px 16px; text-align:left;">Emissor</th>
                            <th class="fi-section-header-description" style="padding:10px 16px; text-align:center;">Emissões</th>
                            <th class="fi-section-header-description" style="padding:10px 16px; text-align:center;">Tempo médio</th>
                            <th class="fi-section-header-description" style="padding:10px 16px; text-align:right;">Valor total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ranking as $i => $row)
                            <tr style="border-top:1px solid rgba(128,128,128,0.1);">
                                <td class="fi-section-header-description" style="padding:10px 16px;">{{ $i + 1 }}</td>
                                <td style="padding:10px 16px; font-weight:500; color:inherit;">{{ $row['name'] }}</td>
                                <td style="padding:10px 16px; text-align:center;">
                                    <span style="display:inline-block; padding:2px 10px; background:rgba(37,99,235,0.15); color:#60a5fa; font-size:12px; font-weight:600; border-radius:9999px;">{{ $row['count'] }}</span>
                                </td>
                                <td class="fi-section-header-description" style="padding:10px 16px; text-align:center;">{{ $row['avg_time'] }}</td>
                                <td style="padding:10px 16px; text-align:right; font-weight:500; color:inherit;">{{ $row['total_value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif

        {{-- Tabela de emissões recentes --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
