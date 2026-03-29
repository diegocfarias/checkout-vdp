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
    style="display: flex; flex-direction: column; gap: 16px;"
>
    {{-- Busca --}}
    <div style="display: flex; gap: 8px; align-items: center;">
        <input
            type="text"
            x-model="query"
            @keydown.enter.prevent="searchImages()"
            placeholder="Buscar imagens no Unsplash..."
            style="flex: 1; padding: 8px 12px; border: 1px solid var(--gray-300, #d1d5db); border-radius: 8px; font-size: 14px; background: var(--white, #fff); color: var(--gray-900, #111827); outline: none;"
        >
        <button
            type="button"
            @click="searchImages()"
            :disabled="loading"
            style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: var(--primary-600, #2563eb); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; white-space: nowrap; opacity: 1;"
            :style="loading ? 'opacity: 0.5; cursor: not-allowed;' : ''"
        >
            <svg x-show="!loading" style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <svg x-show="loading" style="width: 16px; height: 16px; animation: spin 1s linear infinite;" fill="none" viewBox="0 0 24 24"><circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            Buscar
        </button>
    </div>

    {{-- Erro --}}
    <div x-show="error" x-cloak style="padding: 12px; background: #fef9c3; border-radius: 8px; font-size: 14px; color: #854d0e;">
        <span x-text="error"></span>
    </div>

    {{-- Grid de resultados --}}
    <div x-show="photos.length > 0" x-cloak style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
        <template x-for="(photo, index) in photos" :key="index">
            <button
                type="button"
                @click="selectPhoto(photo)"
                style="position: relative; border-radius: 8px; overflow: hidden; border: 2px solid transparent; aspect-ratio: 16/9; cursor: pointer; padding: 0; background: none;"
                :style="selectedUrl === photo.url ? 'border-color: var(--primary-500, #3b82f6); box-shadow: 0 0 0 3px rgba(59,130,246,0.2);' : 'border-color: var(--gray-200, #e5e7eb);'"
            >
                <img :src="photo.thumb || photo.url" :alt="'Foto ' + (index + 1)" style="width: 100%; height: 100%; object-fit: cover;">
                <div x-show="selectedUrl === photo.url" style="position: absolute; inset: 0; background: rgba(59,130,246,0.2); display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 24px; height: 24px; color: #fff; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.5));" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
            </button>
        </template>
    </div>

    {{-- Preview do card + zoom --}}
    <div x-show="selectedUrl" x-cloak style="display: flex; flex-direction: column; gap: 12px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <span style="font-size: 14px; font-weight: 500; color: var(--gray-700, #374151);">Preview do card</span>
            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 12px; color: var(--gray-500, #6b7280);">Zoom:</label>
                <input
                    type="range"
                    min="100"
                    max="200"
                    step="5"
                    :value="zoom"
                    @input="updateZoom($event.target.value)"
                    style="width: 96px; height: 6px; accent-color: var(--primary-600, #2563eb);"
                >
                <span style="font-size: 12px; color: var(--gray-500, #6b7280); width: 32px; text-align: right;" x-text="zoom + '%'"></span>
            </div>
        </div>

        {{-- Card preview --}}
        <div style="max-width: 280px; border-radius: 12px; overflow: hidden; border: 1px solid var(--gray-200, #e5e7eb); background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="position: relative; height: 144px; overflow: hidden;">
                <img
                    :src="selectedUrl"
                    alt="Preview"
                    style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s;"
                    :style="'transform: scale(' + (zoom / 100) + '); transform-origin: center center;'"
                >
            </div>
            <div style="padding: 12px;">
                <h3 style="font-size: 14px; font-weight: 700; color: #111827; margin: 0;" x-text="arrivalCity || 'Cidade de destino'"></h3>
                <p style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                    <span x-text="($wire.get('data.departure_iata') || 'XXX').toUpperCase()"></span>
                    →
                    <span x-text="($wire.get('data.arrival_iata') || 'XXX').toUpperCase()"></span>
                    · Ida e volta
                </p>
                <div style="margin-top: 8px;">
                    <p style="font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin: 0;">a partir de</p>
                    <p style="font-size: 16px; font-weight: 700; color: #111827; margin: 2px 0 0;">R$ --,--</p>
                </div>
            </div>
        </div>

        <p style="font-size: 11px; color: var(--gray-400, #9ca3af);">
            Crédito: <span x-text="selectedCredit"></span>
        </p>
    </div>
</div>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
