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
        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px;">
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

        {{-- Tabela --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
