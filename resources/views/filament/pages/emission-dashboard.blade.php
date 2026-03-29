<x-filament-panels::page>
    <div style="display:flex; flex-direction:column; gap:24px;">

        {{-- Filtro de período --}}
        <div style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:16px; padding:16px 20px; background:var(--fi-body-bg, #fff); border:1px solid rgba(128,128,128,0.2); border-radius:12px;">
            <div>
                <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px; color:var(--fi-fo-field-label-color, #6b7280);">De</label>
                <input type="date" wire:model.live="dateFrom"
                    style="padding:8px 12px; border:1px solid rgba(128,128,128,0.3); border-radius:8px; font-size:14px; background:transparent; color:inherit;">
            </div>
            <div>
                <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px; color:var(--fi-fo-field-label-color, #6b7280);">Até</label>
                <input type="date" wire:model.live="dateTo"
                    style="padding:8px 12px; border:1px solid rgba(128,128,128,0.3); border-radius:8px; font-size:14px; background:transparent; color:inherit;">
            </div>
        </div>

        {{-- Stats --}}
        <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:16px;">
            @foreach($this->getStats() as $stat)
                <div style="padding:16px 20px; background:var(--fi-body-bg, #fff); border:1px solid rgba(128,128,128,0.2); border-radius:12px;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                        <x-filament::icon :icon="$stat['icon']" style="width:20px; height:20px; color:var(--fi-fo-field-label-color, #9ca3af);" />
                        <span style="font-size:12px; font-weight:500; color:var(--fi-fo-field-label-color, #6b7280);">{{ $stat['label'] }}</span>
                    </div>
                    <div style="font-size:24px; font-weight:700; color:inherit;">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Ranking --}}
        @php $ranking = $this->getRanking(); @endphp
        @if(count($ranking) > 0)
            <div style="background:var(--fi-body-bg, #fff); border:1px solid rgba(128,128,128,0.2); border-radius:12px; overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid rgba(128,128,128,0.2);">
                    <h3 style="font-size:15px; font-weight:600; margin:0; color:inherit;">Ranking de Emissores</h3>
                </div>
                <table style="width:100%; font-size:14px; border-collapse:collapse;">
                    <thead>
                        <tr style="background:rgba(128,128,128,0.06);">
                            <th style="padding:12px 16px; text-align:left; font-weight:500; font-size:13px; color:var(--fi-fo-field-label-color, #6b7280);">#</th>
                            <th style="padding:12px 16px; text-align:left; font-weight:500; font-size:13px; color:var(--fi-fo-field-label-color, #6b7280);">Emissor</th>
                            <th style="padding:12px 16px; text-align:center; font-weight:500; font-size:13px; color:var(--fi-fo-field-label-color, #6b7280);">Emissões</th>
                            <th style="padding:12px 16px; text-align:center; font-weight:500; font-size:13px; color:var(--fi-fo-field-label-color, #6b7280);">Tempo médio</th>
                            <th style="padding:12px 16px; text-align:right; font-weight:500; font-size:13px; color:var(--fi-fo-field-label-color, #6b7280);">Valor total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ranking as $i => $row)
                            <tr style="border-top:1px solid rgba(128,128,128,0.15);">
                                <td style="padding:12px 16px; color:var(--fi-fo-field-label-color, #6b7280);">{{ $i + 1 }}</td>
                                <td style="padding:12px 16px; font-weight:500;">{{ $row['name'] }}</td>
                                <td style="padding:12px 16px; text-align:center;">
                                    <span style="display:inline-block; padding:2px 10px; background:rgba(37,99,235,0.1); color:#3b82f6; font-size:12px; font-weight:600; border-radius:9999px;">{{ $row['count'] }}</span>
                                </td>
                                <td style="padding:12px 16px; text-align:center; color:var(--fi-fo-field-label-color, #9ca3af);">{{ $row['avg_time'] }}</td>
                                <td style="padding:12px 16px; text-align:right; font-weight:500;">{{ $row['total_value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Tabela de emissões recentes --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
