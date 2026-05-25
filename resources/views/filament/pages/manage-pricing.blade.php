<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit">
                    Salvar precificação
                </x-filament::button>
            </div>
        </form>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
