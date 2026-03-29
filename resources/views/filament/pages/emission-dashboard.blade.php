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
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
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

        {{-- Ranking --}}
        @php $ranking = $this->getRanking(); @endphp
        @if(count($ranking) > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Ranking de Emissores</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">#</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Emissor</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-500 dark:text-gray-400">Emissões</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-500 dark:text-gray-400">Tempo médio</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Valor total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($ranking as $i => $row)
                            <tr>
                                <td class="px-4 py-3 text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ $row['count'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">{{ $row['avg_time'] }}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">{{ $row['total_value'] }}</td>
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
