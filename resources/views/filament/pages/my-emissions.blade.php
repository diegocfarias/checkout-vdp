<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filtro de período --}}
        <div class="flex flex-wrap items-end gap-4 p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">De</label>
                <input type="date" wire:model.live="dateFrom"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Até</label>
                <input type="date" wire:model.live="dateTo"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($this->getStats() as $stat)
                <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center gap-2 mb-2">
                        <x-filament::icon :icon="$stat['icon']" class="w-5 h-5 text-gray-400" />
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</span>
                    </div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Tabela --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
