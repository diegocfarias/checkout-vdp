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
        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px;">
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

        {{-- Tabela --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
