<x-layouts::app :title="__('IA service area map')">
    {{-- Styles are inline so the page looks right without rebuilding the CSS bundle --}}
    <style>
        .iamap-wrap { position: relative; z-index: 0; isolation: isolate; }
        #ia-map { height: calc(100vh - 17rem); min-height: 26rem; border-radius: 0.75rem; border: 1px solid rgb(228 228 231); background: #e5e7eb; }
        .dark #ia-map { border-color: rgb(255 255 255 / 0.12); }
        .iamap-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; }
        .iamap-select, .iamap-input { border-radius: 0.5rem; border: 1px solid rgb(212 212 216); background: transparent; padding: 0.45rem 0.7rem; font-size: 0.85rem; color: rgb(24 24 27); }
        .iamap-input { min-width: 14rem; }
        .dark .iamap-select, .dark .iamap-input { border-color: rgb(63 63 70); color: #fff; }
        .dark .iamap-select option { color: #000; }
        .iamap-stats { display: flex; flex-wrap: wrap; gap: 1.25rem; font-size: 0.85rem; color: rgb(63 63 70); }
        .dark .iamap-stats { color: rgb(212 212 216); }
        .iamap-stats strong { color: rgb(24 24 27); font-size: 1rem; }
        .dark .iamap-stats strong { color: #fff; }
        .iamap-legend { background: rgb(255 255 255 / 0.95); padding: 0.55rem 0.75rem; border-radius: 0.5rem; box-shadow: 0 1px 6px rgb(0 0 0 / 0.3); font-size: 0.78rem; line-height: 1.5; color: #18181b; }
        .iamap-legend i { display: inline-block; width: 0.8rem; height: 0.8rem; margin-right: 0.4rem; border-radius: 2px; vertical-align: -1px; }
        .iamap-pop { font-size: 0.82rem; line-height: 1.45; min-width: 14rem; }
        .iamap-pop h4 { margin: 0 0 0.15rem; font-size: 0.92rem; font-weight: 600; }
        .iamap-pop .muted { color: #71717a; }
        .iamap-pop a { color: #15803d; font-weight: 600; }
        .iamap-msg { position: absolute; inset: 0; z-index: 800; display: flex; align-items: center; justify-content: center; text-align: center; padding: 1rem; background: rgb(255 255 255 / 0.8); border-radius: 0.75rem; font-size: 0.9rem; color: #3f3f46; }
        .iamap-msg[hidden] { display: none; }
        .dark .iamap-msg { background: rgb(24 24 27 / 0.85); color: #e4e4e7; }
    </style>

    <div id="ia-page" class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('IA service area map') }}</flux:heading>
                <flux:subheading>{{ __('Lots of each IA, from the Albay CIS shapefile (Dec 2025). Click a lot for details.') }}</flux:subheading>
            </div>
            <flux:button variant="ghost" icon="building-office-2" :href="route('ia-profiles.index')" wire:navigate>{{ __('IA Profiles') }}</flux:button>
        </div>

        <div class="iamap-bar">
            <input id="map-search" class="iamap-input" type="search" placeholder="{{ __('Find an IA or CIS...') }}" autocomplete="off">
            <select id="map-province" class="iamap-select"><option value="">{{ __('All provinces') }}</option></select>
            <select id="map-ia-status" class="iamap-select">
                <option value="">{{ __('Any IA status') }}</option>
                <option value="operational">{{ __('Operational IAs') }}</option>
                <option value="non-operational">{{ __('Non-operational IAs') }}</option>
            </select>
            <select id="map-lot-status" class="iamap-select">
                <option value="">{{ __('All lot types') }}</option>
                @foreach ($lotStatuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select id="map-color" class="iamap-select" title="{{ __('Colour by') }}">
                <option value="lot">{{ __('Colour: lot type') }}</option>
                <option value="ia">{{ __('Colour: IA status') }}</option>
                <option value="system">{{ __('Colour: system') }}</option>
            </select>
            <button type="button" id="map-reset" class="ut-btn">{{ __('Reset') }}</button>
        </div>

        <div class="iamap-stats">
            <span><strong id="map-stat-ias">—</strong> {{ __('IAs') }}</span>
            <span><strong id="map-stat-lots">—</strong> {{ __('lots') }}</span>
            <span><strong id="map-stat-ha">—</strong> {{ __('ha mapped') }}</span>
        </div>

        <div class="iamap-wrap">
            <div id="ia-map"
                 data-url="{{ route('ia-map.data') }}"
                 data-focus="{{ $focusId }}"
                 data-lot-statuses="{{ json_encode($lotStatuses) }}"></div>
            <div id="map-msg" class="iamap-msg">{{ __('Loading map...') }}</div>
        </div>
        <p class="ut-sub">{{ __('Base maps: OpenStreetMap and Esri World Imagery (need an internet connection). Lot types come from the Remarks column of the shapefile; an empty remark is shown as operational / irrigated.') }}</p>
    </div>

    {{-- The map script lives here (not in the JS bundle) so this page works even if app.js was not rebuilt. --}}
    @verbatim
    <script>
// IA service area map (Leaflet, loaded from a CDN so no npm install is needed).
const LEAFLET = {
    js: ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo='],
    css: ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY='],
};

const COLORS = {
    lot: { op: '#16a34a', non_op: '#dc2626', converted: '#6b7280', da_area: '#f59e0b', pnra: '#8b5cf6' },
    ia: { operational: '#16a34a', 'non-operational': '#dc2626' },
    system: { CIS: '#16a34a', NIS: '#2563eb', SPIP: '#f59e0b', none: '#6b7280' },
};
const IA_LABELS = { operational: 'Operational IA', 'non-operational': 'Non-operational IA' };
const SYSTEM_LABELS = { CIS: 'CIS (communal)', NIS: 'NIS (national)', SPIP: 'SPIP (solar pump)', none: 'No system recorded' };

const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const num = (v, d = 2) => Number(v ?? 0).toLocaleString(undefined, { maximumFractionDigits: d });

function loadLeaflet() {
    if (window.L) {
        return Promise.resolve(window.L);
    }

    return new Promise((resolve, reject) => {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = LEAFLET.css[0];
        css.integrity = LEAFLET.css[1];
        css.crossOrigin = '';
        document.head.appendChild(css);

        const js = document.createElement('script');
        js.src = LEAFLET.js[0];
        js.integrity = LEAFLET.js[1];
        js.crossOrigin = '';
        js.onload = () => resolve(window.L);
        js.onerror = () => reject(new Error('Could not load the map library. Check the internet connection.'));
        document.head.appendChild(js);
    });
}

async function runMap() {
    const el = document.getElementById('ia-map');

    if (!el || el.dataset.ready) {
        return;
    }

    el.dataset.ready = '1';

    const msg = document.getElementById('map-msg');
    const say = (text) => { msg.textContent = text; msg.hidden = !text; };
    const lotLabels = JSON.parse(el.dataset.lotStatuses || '{}');

    let L;
    let data;

    try {
        [L, data] = await Promise.all([
            loadLeaflet(),
            fetch(el.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((r) => {
                if (!r.ok) throw new Error(r.status === 403 ? 'You do not have permission to view the map.' : 'Could not load the map data.');

                return r.json();
            }),
        ]);
    } catch (error) {
        say(error.message);

        return;
    }

    const { ias, lots } = data;

    if (!lots.features.length) {
        say('No map data yet. Import database/ia_map_lots.sql, then reload this page.');

        return;
    }

    // mapped area per IA (for the popup)
    const mapped = {};
    lots.features.forEach((f) => {
        const m = (mapped[f.properties.i] ??= { lots: 0, ha: 0 });
        m.lots += 1;
        m.ha += f.properties.a;
    });

    const map = L.map(el, { preferCanvas: true, zoomControl: true });
    const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' });
    const sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Tiles &copy; Esri' });
    osm.addTo(map);
    L.control.layers({ Street: osm, Satellite: sat }, null, { position: 'topright' }).addTo(map);
    L.control.scale({ imperial: false }).addTo(map);

    // --- filters
    const f = {
        search: document.getElementById('map-search'),
        province: document.getElementById('map-province'),
        iaStatus: document.getElementById('map-ia-status'),
        lotStatus: document.getElementById('map-lot-status'),
        color: document.getElementById('map-color'),
    };

    [...new Map(Object.values(ias).filter((i) => i.province_id).map((i) => [i.province_id, i.province])).entries()]
        .sort((a, b) => String(a[1]).localeCompare(String(b[1])))
        .forEach(([id, name]) => f.province.add(new Option(name, id)));

    let selected = null;

    const colorOf = (p) => {
        const ia = ias[p.i];
        const mode = f.color.value;

        if (mode === 'ia') return COLORS.ia[ia?.status] ?? '#6b7280';
        if (mode === 'system') return COLORS.system[ia?.systems?.[0] ?? 'none'];

        return COLORS.lot[p.l] ?? '#6b7280';
    };

    const style = (feature) => {
        const on = feature.properties.i === selected;

        return { color: on ? '#111827' : colorOf(feature.properties), weight: on ? 3 : 1, fillColor: colorOf(feature.properties), fillOpacity: on ? 0.8 : 0.5 };
    };

    const matches = (feature) => {
        const p = feature.properties;
        const ia = ias[p.i];
        const q = f.search.value.trim().toLowerCase();

        if (!ia) return false;
        if (f.province.value && String(ia.province_id) !== f.province.value) return false;
        if (f.iaStatus.value && ia.status !== f.iaStatus.value) return false;
        if (f.lotStatus.value && p.l !== f.lotStatus.value) return false;
        if (q && !`${ia.name} ${ia.code ?? ''} ${ia.cis ?? ''}`.toLowerCase().includes(q)) return false;

        return true;
    };

    const popup = (feature) => {
        const p = feature.properties;
        const ia = ias[p.i];
        const m = mapped[p.i];
        const systems = (ia.systems ?? []).join(' · ') || '—';

        return `<div class="iamap-pop">
            <h4>${esc(ia.name)}</h4>
            <div class="muted">${esc([ia.code, ia.cis].filter(Boolean).join(' · '))}</div>
            <div>${esc(ia.province ?? '')}${p.m ? ` · ${esc(p.m)}` : ''}</div>
            <div>${esc(ia.status_label)} · ${esc(systems)}</div>
            <hr style="margin:.35rem 0;border:0;border-top:1px solid #e4e4e7">
            <div><strong>This lot:</strong> ${num(p.a, 4)} ha · ${esc(lotLabels[p.l] ?? p.l)}${p.n ? ` (${esc(p.n)})` : ''}</div>
            <div><strong>Mapped:</strong> ${m.lots} lots · ${num(m.ha)} ha${ia.service_area != null ? ` of ${num(ia.service_area)} ha service area` : ''}</div>
            <div style="margin-top:.35rem"><a href="${esc(ia.url)}">Open IA profile &rarr;</a></div>
        </div>`;
    };

    const layer = L.geoJSON(null, {
        style,
        onEachFeature: (feature, lyr) => {
            lyr.bindTooltip(esc(ias[feature.properties.i]?.name ?? ''), { sticky: true });
            lyr.on('click', (event) => {
                selected = feature.properties.i;
                layer.setStyle(style);
                L.popup({ maxWidth: 340 }).setLatLng(event.latlng).setContent(popup(feature)).openOn(map);
            });
        },
    }).addTo(map);

    // Opening a popup closes the previous one first, so only clear the highlight if nothing is open afterwards.
    map.on('popupclose', () => setTimeout(() => {
        if (!el.querySelector('.leaflet-popup')) {
            selected = null;
            layer.setStyle(style);
        }
    }, 0));

    // --- legend
    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = () => L.DomUtil.create('div', 'iamap-legend');
    legend.addTo(map);

    const drawLegend = () => {
        const mode = f.color.value;
        const entries = mode === 'ia' ? Object.entries(COLORS.ia).map(([k, c]) => [IA_LABELS[k], c])
            : mode === 'system' ? Object.entries(COLORS.system).map(([k, c]) => [SYSTEM_LABELS[k], c])
                : Object.entries(COLORS.lot).map(([k, c]) => [lotLabels[k] ?? k, c]);

        legend.getContainer().innerHTML = entries.map(([label, c]) => `<div><i style="background:${c}"></i>${esc(label)}</div>`).join('');
    };

    // --- render
    const render = (fit = true) => {
        const shown = lots.features.filter(matches);
        layer.clearLayers();
        layer.addData({ type: 'FeatureCollection', features: shown });

        document.getElementById('map-stat-ias').textContent = num(new Set(shown.map((x) => x.properties.i)).size, 0);
        document.getElementById('map-stat-lots').textContent = num(shown.length, 0);
        document.getElementById('map-stat-ha').textContent = num(shown.reduce((s, x) => s + x.properties.a, 0));
        say(shown.length ? '' : 'No lots match these filters.');
        drawLegend();

        if (fit && shown.length) {
            map.fitBounds(layer.getBounds(), { padding: [20, 20], maxZoom: 16 });
        }
    };

    [f.province, f.iaStatus, f.lotStatus].forEach((s) => s.addEventListener('change', () => render()));
    f.search.addEventListener('input', () => render());
    f.color.addEventListener('change', () => { layer.setStyle(style); drawLegend(); });
    document.getElementById('map-reset').addEventListener('click', () => {
        f.search.value = f.province.value = f.iaStatus.value = f.lotStatus.value = '';
        f.color.value = 'lot';
        render();
    });

    render();
    say('');
    setTimeout(() => map.invalidateSize(), 150);

    // ?ia=ID (from the profile page): zoom to that IA and show its details
    const focus = Number(el.dataset.focus);

    if (focus && ias[focus]) {
        const own = lots.features.filter((x) => x.properties.i === focus);
        const bounds = L.geoJSON({ type: 'FeatureCollection', features: own }).getBounds();

        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
            selected = focus;
            layer.setStyle(style);
            L.popup({ maxWidth: 340 }).setLatLng(bounds.getCenter()).setContent(popup(own[0])).openOn(map);
        }
    }
}

// Run now (the script sits after the map element) and never fail silently.
(async () => {
    const note = document.getElementById('map-msg');
    let finished = false;
    const watchdog = setTimeout(() => {
        if (!finished && note && !note.hidden) {
            note.textContent = 'Still loading... If this stays, check that this computer can reach unpkg.com and openstreetmap.org, then press Ctrl+F5.';
        }
    }, 15000);

    try {
        await runMap();
    } catch (error) {
        console.error('IA map failed', error);
        if (note) {
            note.hidden = false;
            note.textContent = 'The map could not start: ' + (error && error.message ? error.message : error);
        }
    } finally {
        finished = true;
        clearTimeout(watchdog);
    }
})();
    </script>
    @endverbatim
</x-layouts::app>
