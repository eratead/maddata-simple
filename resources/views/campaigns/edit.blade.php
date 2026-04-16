<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>

<x-app-layout>

@push('page-title')
    <nav class="flex items-center gap-1.5 text-xs text-gray-400">
        <a href="{{ route('campaigns.index') }}" class="hover:text-[#F97316] transition-colors">Campaigns</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18l6-6-6-6"/>
        </svg>
        <span class="font-semibold text-gray-700 max-w-[200px] truncate">{{ $campaign->name }}</span>
    </nav>
@endpush

@push('page-actions')
    <button type="button" @click="window.dispatchEvent(new CustomEvent('campaign:open-summary'))"
        class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs font-semibold text-gray-600 hover:bg-gray-50 hover:border-gray-300 transition-all">
        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Summary
    </button>

    <button type="button" @click="window.dispatchEvent(new CustomEvent('campaign:open-ai'))"
        class="inline-flex items-center gap-1.5 px-3 py-2 bg-violet-50 border border-violet-200 rounded-lg text-xs font-semibold text-violet-700 hover:bg-violet-100 transition-all">
        <svg class="w-3.5 h-3.5 text-violet-500" viewBox="0 0 24 24" fill="currentColor">
            <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>
        </svg>
        AI Brief
    </button>

    <a href="{{ route('campaigns.index') }}"
        class="inline-flex items-center px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all">
        Cancel
    </a>

    <button type="submit" form="campaignForm"
        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#F97316] hover:bg-orange-600 text-white rounded-lg text-xs font-bold shadow-sm transition-all hover:-translate-y-0.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        Save Changes
    </button>
@endpush

    <div x-data="campaignAssistant()">

        <x-flash-messages />

        <div>

            <form id="campaignForm" class="flex flex-col gap-4"
                action="{{ route('campaigns.update', $campaign->id) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- Details Card --}}
                <x-campaign.details-card :campaign="$campaign" :clients="$clients" />

                {{-- Schedule Card --}}
                <x-campaign.schedule-card :campaign="$campaign" />

                {{-- Accordions --}}
                <div class="flex flex-col gap-2">
                    <x-campaign.sizes-accordion :campaign="$campaign" />
                    <x-campaign.creatives-accordion :campaign="$campaign" />
                    <x-campaign.audiences-accordion :campaign="$campaign" :connected-audiences="$connectedAudiences" />
                    <x-campaign.targeting-accordion :campaign="$campaign" />
                </div>

                {{-- Bottom Save --}}
                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#F97316] hover:bg-orange-600 text-white rounded-xl text-sm font-bold shadow-sm transition-all hover:-translate-y-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Changes
                    </button>
                </div>

            </form>
        </div>

        {{-- AI Brief Panel --}}
        <x-campaign.ai-brief-panel />

        {{-- Summary Modal --}}
        <x-campaign.summary-modal :campaign="$campaign" />

    </div>

@push('scripts')
<script>
function audienceManager(campaignId, initialConnected) {
    return {
        campaignId,
        connected: initialConnected,
        showModal: false,
        open: false,
        allAudiences: [],
        loading: false,
        syncing: false,
        search: '',
        filterConnected: false,
        activeCategory: null,
        selectedIds: [],

        get mainCategories() {
            const seen = new Map();
            this.allAudiences.forEach(a => {
                if (!seen.has(a.main_category)) seen.set(a.main_category, { name: a.main_category, icon: a.icon });
            });
            return Array.from(seen.values());
        },
        categoryHasSelected(cat) {
            return this.allAudiences.some(a => a.main_category === cat && this.selectedIds.includes(a.id));
        },
        get filteredAudiences() {
            return this.allAudiences.filter(a => {
                if (this.activeCategory && a.main_category !== this.activeCategory) return false;
                if (this.filterConnected && !this.selectedIds.includes(a.id)) return false;
                if (this.search) {
                    const q = this.search.toLowerCase();
                    return a.name.toLowerCase().includes(q) || a.main_category.toLowerCase().includes(q) || a.sub_category.toLowerCase().includes(q);
                }
                return true;
            });
        },
        get groupedBySub() {
            const groups = {};
            this.filteredAudiences.forEach(a => {
                if (!groups[a.sub_category]) groups[a.sub_category] = [];
                groups[a.sub_category].push(a);
            });
            return groups;
        },
        get selectedCount() { return this.selectedIds.length; },
        isSelected(id) { return this.selectedIds.includes(id); },
        toggle(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx > -1) this.selectedIds.splice(idx, 1);
            else this.selectedIds.push(id);
        },
        async openModal() {
            this.selectedIds = this.connected.map(a => a.id);
            this.showModal = true;
            if (this.allAudiences.length === 0) {
                this.loading = true;
                try {
                    const res = await fetch(`/campaigns/${this.campaignId}/audiences`);
                    this.allAudiences = await res.json();
                    this.activeCategory = null;
                } finally { this.loading = false; }
            }
        },
        async applySync() {
            this.syncing = true;
            try {
                const res = await fetch(`/campaigns/${this.campaignId}/audiences/sync`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ audience_ids: this.selectedIds }),
                });
                const data = await res.json();
                this.connected = data.connected;
                this.showModal = false;
            } finally { this.syncing = false; }
        },
        async removeAudience(id) {
            this.selectedIds = this.connected.map(a => a.id).filter(i => i !== id);
            const res = await fetch(`/campaigns/${this.campaignId}/audiences/sync`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ audience_ids: this.selectedIds }),
            });
            const data = await res.json();
            this.connected = data.connected;
        },
        formatUsers(n) {
            if (!n) return '—';
            if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M Users';
            if (n >= 1000) return Math.round(n / 1000) + 'K Users';
            return n + ' Users';
        },
    };
}

function targetingData(initial) {
    return {
        ...initial,
        open: false,
        isAiOpen: false,
        aiPrompt: '',
        isAiLoading: false,
        newLocation: { name: '', lat: '', lng: '' },
        searchQuery: '',
        searchResults: [],
        showEditModal: false,
        editingIndex: null,
        editingLocation: { name: '', lat: '', lng: '', radius_km: 1 },
        countryInput: '',
        regionInput: '',
        cityInput: '',
        showCountrySug: false,
        showRegionSug: false,
        showCitySug: false,
        geoCountriesList: [],
        geoRegionsList: [],
        geoCitiesList: [],
        geoLoadingRegions: false,
        geoLoadingCities: false,

        get filteredCountrySug() {
            const q = this.countryInput.trim().toLowerCase();
            const list = q ? this.geoCountriesList.filter(c => c.toLowerCase().includes(q)) : this.geoCountriesList;
            return list.filter(c => !this.countries.includes(c)).slice(0, 10);
        },
        get filteredRegionSug() {
            const q = this.regionInput.trim().toLowerCase();
            const list = q ? this.geoRegionsList.filter(r => r.toLowerCase().includes(q)) : this.geoRegionsList;
            return list.filter(r => !this.regions.includes(r)).slice(0, 10);
        },
        get filteredCitySug() {
            const q = this.cityInput.trim().toLowerCase();
            const list = q ? this.geoCitiesList.filter(c => c.toLowerCase().includes(q)) : this.geoCitiesList;
            return list.filter(c => !this.cities.includes(c)).slice(0, 10);
        },

        init() {
            this._map = null;
            this._layers = [];
            this._clickMarker = null;
            this.loadCountriesList();
            if (this.countries.length) this.loadGeoData();
            this.$watch('open', (val) => {
                if (val && this.activeTab === 'geo') setTimeout(() => this._initMap(), 50);
            });
            this.$watch('activeTab', (tab) => {
                if (tab === 'geo') {
                    setTimeout(() => {
                        if (!this._map) this._initMap();
                        else this._map.invalidateSize();
                    }, 50);
                }
            });
        },
        _initMap() {
            if (this._map) return;
            this._map = L.map('geo-map').setView([31.5, 34.8], 8);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
            }).addTo(this._map);
            this._map.on('click', (e) => {
                const lat = e.latlng.lat.toFixed(6);
                const lng = e.latlng.lng.toFixed(6);
                this.newLocation.lat = lat;
                this.newLocation.lng = lng;
                if (this._clickMarker) this._clickMarker.remove();
                this._clickMarker = L.circleMarker([lat, lng], {
                    radius: 7, color: '#F97316', fillColor: '#F97316', fillOpacity: 0.9, weight: 2,
                }).addTo(this._map);
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=en`)
                    .then(r => r.json())
                    .then(data => {
                        const a = data.address || {};
                        this.newLocation.name = a.neighbourhood || a.suburb || a.city_district || a.town || a.city || a.county || (data.display_name || '').split(',')[0];
                    }).catch(() => {});
            });
            this.drawMarkers();
            setTimeout(() => this._map && this._map.invalidateSize(), 100);
        },
        drawMarkers() {
            if (!this._map) return;
            this._layers.forEach(l => l.remove());
            this._layers = [];
            this.locations.forEach((loc, i) => {
                const lat = parseFloat(loc.lat), lng = parseFloat(loc.lng);
                if (isNaN(lat) || isNaN(lng)) return;
                const m = L.marker([lat, lng]);
                if (loc.name) m.bindTooltip(loc.name, { permanent: true, direction: 'top', offset: [0, -8] });
                m.on('click', () => this.openEdit(i));
                m.addTo(this._map);
                const c = L.circle([lat, lng], { radius: loc.radius_meters, color: '#2563EB', weight: 2, fillOpacity: 0.1 }).addTo(this._map);
                this._layers.push(m, c);
            });
        },
        addLocation() {
            if (this.newLocation.lat !== '' && this.newLocation.lng !== '') {
                this.locations.push({ name: this.newLocation.name, lat: this.newLocation.lat, lng: this.newLocation.lng, radius_meters: 1000 });
                this.newLocation = { name: '', lat: '', lng: '' };
                if (this._clickMarker) { this._clickMarker.remove(); this._clickMarker = null; }
                this.drawMarkers();
            }
        },
        removeLocation(index) { this.locations.splice(index, 1); this.drawMarkers(); },
        _fmt(arr, total) { if (!arr || arr.length === 0 || arr.length >= total) return 'All'; return arr.join(', '); },
        summaryGeo() {
            const parts = [];
            parts.push('Countries: ' + (this.countries.length > 0 ? this.countries.join(', ') : 'All'));
            if (this.regions.length > 0) parts.push('Regions: ' + this.regions.join(', '));
            if (this.cities.length > 0) parts.push('Cities: ' + this.cities.join(', '));
            if (this.locations.length > 0) {
                const locs = this.locations.map(l => {
                    const lat = parseFloat(l.lat).toFixed(4), lng = parseFloat(l.lng).toFixed(4);
                    const km = Math.round((l.radius_meters || 1000) / 1000);
                    return `${l.name || 'Unnamed'} (${lat}, ${lng}, ${km}km)`;
                }).join('  ·  ');
                parts.push('Proximity: ' + locs);
            }
            return parts.join('  ·  ') || 'All';
        },
        async loadCountriesList() {
            try {
                const res = await fetch('/api/geo/countries');
                const data = await res.json();
                if (data.data) this.geoCountriesList = data.data;
            } catch {}
        },
        async loadGeoData() {
            if (!this.countries.length) { this.geoRegionsList = []; this.geoCitiesList = []; return; }
            const country = this.countries[this.countries.length - 1];
            this.geoLoadingRegions = true;
            this.geoLoadingCities = true;
            try {
                const enc = encodeURIComponent(country);
                const [rRes, cRes] = await Promise.all([
                    fetch(`/api/geo/regions?country=${enc}`),
                    fetch(`/api/geo/cities?country=${enc}`),
                ]);
                const [rData, cData] = await Promise.all([rRes.json(), cRes.json()]);
                if (rData.data) this.geoRegionsList = rData.data;
                if (cData.data) this.geoCitiesList = cData.data;
            } catch {}
            this.geoLoadingRegions = false;
            this.geoLoadingCities = false;
        },
        addCountry(name) {
            const c = (name ?? this.countryInput).trim();
            if (c && !this.countries.includes(c)) { this.countries.push(c); this.loadGeoData(); }
            this.countryInput = ''; this.showCountrySug = false;
        },
        removeCountry(index) { this.countries.splice(index, 1); this.loadGeoData(); },
        addRegion(name) {
            const r = (name ?? this.regionInput).trim();
            if (r && !this.regions.includes(r)) this.regions.push(r);
            this.regionInput = ''; this.showRegionSug = false;
        },
        removeRegion(index) { this.regions.splice(index, 1); },
        addCity(name) {
            const c = (name ?? this.cityInput).trim();
            if (c && !this.cities.includes(c)) this.cities.push(c);
            this.cityInput = ''; this.showCitySug = false;
        },
        removeCity(index) { this.cities.splice(index, 1); },
        openEdit(index) {
            this.editingIndex = index;
            const loc = this.locations[index];
            this.editingLocation = { name: loc.name, lat: loc.lat, lng: loc.lng, radius_km: Math.max(1, Math.round((loc.radius_meters || 1000) / 1000)) };
            this.showEditModal = true;
        },
        closeEdit() { this.showEditModal = false; this.editingIndex = null; },
        saveEdit() {
            const radius_meters = (this.editingLocation.radius_km || 1) * 1000;
            if (this.editingIndex !== null) {
                this.locations[this.editingIndex] = { name: this.editingLocation.name, lat: this.editingLocation.lat, lng: this.editingLocation.lng, radius_meters };
                this.drawMarkers();
            } else {
                this.newLocation.name = this.editingLocation.name;
                this.newLocation.lat = this.editingLocation.lat;
                this.newLocation.lng = this.editingLocation.lng;
            }
            this.closeEdit();
        },
        deleteEdit() { if (this.editingIndex !== null) this.removeLocation(this.editingIndex); this.closeEdit(); },
        searchPlace() {
            const q = this.searchQuery.trim();
            if (q.length < 2) { this.searchResults = []; return; }
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=6&accept-language=en`)
                .then(res => res.json()).then(data => { this.searchResults = data; }).catch(() => {});
        },
        selectResult(result) {
            this.newLocation.lat = result.lat;
            this.newLocation.lng = result.lon;
            this.newLocation.name = result.display_name.split(',')[0];
            this.searchQuery = '';
            this.searchResults = [];
            if (this._map) {
                this._map.setView([parseFloat(result.lat), parseFloat(result.lon)], 13);
                if (this._clickMarker) this._clickMarker.remove();
                this._clickMarker = L.circleMarker([result.lat, result.lon], {
                    radius: 7, color: '#F97316', fillColor: '#F97316', fillOpacity: 0.9, weight: 2,
                }).addTo(this._map);
            }
        },
        async generateAiLocations() {
            if (this.isAiLoading || this.aiPrompt.trim() === '') return;
            this.isAiLoading = true;
            try {
                const res = await fetch('/ai/generate-locations', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                    body: JSON.stringify({ prompt: this.aiPrompt }),
                });
                if (!res.ok) throw new Error('Request failed');
                const data = await res.json();
                data.forEach(loc => this.locations.push({ name: loc.name, lat: loc.lat, lng: loc.lng, radius_meters: 1000 }));
                this.drawMarkers();
            } catch (e) { console.error('AI location generation failed:', e); }
            finally { this.aiPrompt = ''; this.isAiOpen = false; this.isAiLoading = false; }
        },
    };
}

function campaignAssistant() {
    return {
        isOpen: false,
        summaryOpen: false,
        messages: [],
        inputText: '',
        isTyping: false,

        init() {
            window.addEventListener('campaign:open-summary', () => this.summaryOpen = true);
            window.addEventListener('campaign:open-ai', () => this.isOpen = true);
        },

        getFormData() {
            const val = (name) => (document.querySelector(`[name="${name}"]`)?.value ?? '');
            let targeting = {};
            const targetingEl = document.querySelector('[x-data^="targetingData"]');
            if (targetingEl) {
                const td = Alpine.$data(targetingEl);
                targeting = { genders: td.genders, ages: td.ages, incomes: td.incomes, deviceTypes: td.deviceTypes, os: td.os, connectionTypes: td.connectionTypes, environments: td.environments, days: td.days, timeStart: td.timeStart, timeEnd: td.timeEnd, countries: td.countries, regions: td.regions, cities: td.cities };
            }
            let audience_ids = [];
            const audienceEl = document.querySelector('[x-data^="audienceManager"]');
            if (audienceEl) audience_ids = Alpine.$data(audienceEl).connected.map(a => a.id).filter(Boolean);
            return { name: val('name'), budget: val('budget'), expected_impressions: val('expected_impressions'), start_date: val('start_date'), end_date: val('end_date'), status: val('status'), targeting, audience_ids };
        },

        applyUpdates(updates) {
            if (!updates) return;
            ['name', 'budget', 'expected_impressions'].forEach(field => {
                if (updates[field] !== undefined) {
                    const el = document.querySelector(`[name="${field}"]`);
                    if (el && !el.disabled) { el.value = updates[field]; el.dispatchEvent(new Event('input', {bubbles:true})); el.dispatchEvent(new Event('change', {bubbles:true})); }
                }
            });
            // Dates: update Alpine Date objects on the schedule card
            if (updates.start_date || updates.end_date) {
                const scheduleEl = document.querySelector('[x-data] input[name="start_date"]');
                if (scheduleEl) {
                    const sd = Alpine.$data(scheduleEl.closest('[x-data]'));
                    if (updates.start_date) sd.dateFrom = new Date(updates.start_date + 'T12:00:00');
                    if (updates.end_date) sd.dateTo = new Date(updates.end_date + 'T12:00:00');
                }
            }
            if (updates.status !== undefined) {
                const statusInput = document.querySelector('[name="status"]');
                if (statusInput) { const root = statusInput.closest('[x-data]'); if (root) Alpine.$data(root).state = updates.status; }
            }
            const targetingEl = document.querySelector('[x-data^="targetingData"]');
            if (targetingEl) {
                const td = Alpine.$data(targetingEl);
                ['genders','ages','incomes','environments','days','countries','regions','cities'].forEach(f => { if (updates[f] !== undefined) td[f] = updates[f]; });
                if (updates.timeStart !== undefined) td.timeStart = updates.timeStart;
                if (updates.timeEnd !== undefined) td.timeEnd = updates.timeEnd;
            }
            if (updates.audience_ids !== undefined && Array.isArray(updates.audience_ids)) {
                const audienceEl = document.querySelector('[x-data^="audienceManager"]');
                if (audienceEl) {
                    const am = Alpine.$data(audienceEl);
                    fetch(`/campaigns/${am.campaignId}/audiences/sync`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ audience_ids: updates.audience_ids }),
                    }).then(r => r.json()).then(data => { am.connected = data.connected; });
                }
            }
        },

        async sendMessage() {
            const text = this.inputText.trim();
            if (!text || this.isTyping) return;
            this.messages.push({ role: 'user', content: text });
            this.inputText = '';
            this.isTyping = true;
            this.scrollToBottom();
            try {
                const res = await fetch('/ai/campaign-assistant', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                    body: JSON.stringify({ chatHistory: this.messages, currentFormData: this.getFormData() }),
                });
                if (!res.ok) throw new Error('Request failed');
                const data = await res.json();
                this.messages.push({ role: 'ai', content: data.reply ?? 'Could not process that request.' });
                if (data.updates) this.applyUpdates(data.updates);
            } catch (e) {
                this.messages.push({ role: 'ai', content: 'Something went wrong. Please try again.' });
            } finally { this.isTyping = false; this.scrollToBottom(); }
        },

        scrollToBottom() {
            this.$nextTick(() => { const el = this.$refs.messagesArea; if (el) el.scrollTop = el.scrollHeight; });
        },
    };
}
</script>
@endpush

</x-app-layout>
