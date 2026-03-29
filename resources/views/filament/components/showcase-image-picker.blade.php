<div
    x-data="{
        query: $wire.get('data.image_search_query') || '',
        photos: [],
        loading: false,
        error: null,
        selectedUrl: $wire.get('data.image_url') || '',
        selectedCredit: $wire.get('data.image_credit') || '',
        zoom: parseInt($wire.get('data.image_zoom') || 100),
        arrivalCity: $wire.get('data.arrival_city') || '',

        async searchImages() {
            const searchQuery = this.query || (this.arrivalCity + ' tourism landmark sightseeing');
            if (!searchQuery.trim()) return;

            this.loading = true;
            this.error = null;
            this.photos = [];

            try {
                const response = await fetch('{{ route('admin.showcase.search-images') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ query: searchQuery })
                });

                if (!response.ok) {
                    if (response.status === 429 || response.status === 403) {
                        this.error = 'Limite de buscas do Unsplash atingido. Tente novamente em alguns minutos.';
                    } else {
                        this.error = 'Erro ao buscar imagens. Tente novamente.';
                    }
                    return;
                }

                const data = await response.json();
                this.photos = data.photos || [];

                if (this.photos.length === 0) {
                    this.error = 'Nenhuma imagem encontrada para essa busca.';
                }
            } catch (e) {
                this.error = 'Erro de conexão. Tente novamente.';
            } finally {
                this.loading = false;
            }
        },

        selectPhoto(photo) {
            this.selectedUrl = photo.url;
            this.selectedCredit = photo.credit;
            $wire.set('data.image_url', photo.url);
            $wire.set('data.image_credit', photo.credit);
        },

        updateZoom(val) {
            this.zoom = parseInt(val);
            $wire.set('data.image_zoom', this.zoom);
        }
    }"
    class="space-y-4"
>
    {{-- Busca --}}
    <div class="flex gap-2">
        <input
            type="text"
            x-model="query"
            @keydown.enter.prevent="searchImages()"
            placeholder="Buscar imagens no Unsplash..."
            class="fi-input block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        >
        <button
            type="button"
            @click="searchImages()"
            :disabled="loading"
            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50 whitespace-nowrap"
        >
            <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            Buscar
        </button>
    </div>

    {{-- Erro --}}
    <div x-show="error" x-cloak class="rounded-lg bg-yellow-50 dark:bg-yellow-900/20 p-3 text-sm text-yellow-700 dark:text-yellow-300">
        <span x-text="error"></span>
    </div>

    {{-- Grid de resultados --}}
    <div x-show="photos.length > 0" x-cloak class="grid grid-cols-3 gap-2">
        <template x-for="(photo, index) in photos" :key="index">
            <button
                type="button"
                @click="selectPhoto(photo)"
                class="relative rounded-lg overflow-hidden border-2 transition-all duration-200 aspect-video group"
                :class="selectedUrl === photo.url ? 'border-primary-500 ring-2 ring-primary-500/30' : 'border-gray-200 dark:border-gray-600 hover:border-primary-300'"
            >
                <img :src="photo.thumb || photo.url" :alt="'Foto ' + (index + 1)" class="w-full h-full object-cover">
                <div x-show="selectedUrl === photo.url" class="absolute inset-0 bg-primary-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white drop-shadow-lg" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
                <div class="absolute bottom-0 left-0 right-0 bg-black/50 px-1.5 py-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <p class="text-[10px] text-white truncate" x-text="photo.credit"></p>
                </div>
            </button>
        </template>
    </div>

    {{-- Preview do card + zoom --}}
    <div x-show="selectedUrl" x-cloak class="space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Preview do card</h4>
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500 dark:text-gray-400">Zoom:</label>
                <input
                    type="range"
                    min="100"
                    max="200"
                    step="5"
                    :value="zoom"
                    @input="updateZoom($event.target.value)"
                    class="w-24 h-1.5 accent-primary-600"
                >
                <span class="text-xs text-gray-500 dark:text-gray-400 w-8 text-right" x-text="zoom + '%'"></span>
            </div>
        </div>

        {{-- Card preview --}}
        <div class="max-w-xs rounded-xl overflow-hidden border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm">
            <div class="relative h-36 overflow-hidden">
                <img
                    :src="selectedUrl"
                    alt="Preview"
                    class="w-full h-full object-cover transition-transform duration-300"
                    :style="'transform: scale(' + (zoom / 100) + '); transform-origin: center center;'"
                >
            </div>
            <div class="p-3">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="arrivalCity || 'Cidade de destino'"></h3>
                <p class="text-xs text-gray-400 mt-1">
                    <span x-text="($wire.get('data.departure_iata') || 'XXX').toUpperCase()"></span>
                    →
                    <span x-text="($wire.get('data.arrival_iata') || 'XXX').toUpperCase()"></span>
                    · Ida e volta
                </p>
                <div class="mt-2">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide">a partir de</p>
                    <p class="text-base font-bold text-gray-900 dark:text-white">R$ --,--</p>
                </div>
            </div>
        </div>

        <p class="text-[11px] text-gray-400 dark:text-gray-500">
            Crédito: <span x-text="selectedCredit"></span>
        </p>
    </div>
</div>
