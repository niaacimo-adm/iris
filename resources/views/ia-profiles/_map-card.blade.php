{{-- ===== Service area map: summary + upload / replace / remove (ia.edit). Self-contained script, no bundle needed. ===== --}}
@php
    $mapLots = (int) ($mapInfo->lots ?? 0);
    $mapUpdated = $mapInfo?->updated ? \Illuminate\Support\Carbon::parse($mapInfo->updated)->format('M d, Y') : null;
@endphp
<section class="ia-card" id="ia-map-card"
         data-store-url="{{ route('ia-profiles.map.store', $profile) }}"
         data-destroy-url="{{ route('ia-profiles.map.destroy', $profile) }}"
         data-existing="{{ $mapLots }}"
         data-name="{{ $profile->ia_name }}"
         data-service-area="{{ $profile->service_area_ha }}">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h3 class="ia-section-title" style="margin:0">{{ __('Service area map') }}</h3>
        <div class="flex flex-wrap items-center gap-2">
            @if ($mapLots > 0 && ! $archived)
                <flux:button size="sm" variant="ghost" icon="globe-asia-australia" :href="route('ia-map.index', ['ia' => $profile->id])">{{ __('View on map') }}</flux:button>
            @endif
            @if ($canEdit)
                <flux:button size="sm" variant="primary" icon="arrow-up-tray" type="button" data-map-upload>{{ $mapLots > 0 ? __('Replace / add map file') : __('Upload map file') }}</flux:button>
                @if ($mapLots > 0)
                    <flux:button size="sm" variant="danger" icon="trash" type="button" data-map-remove>{{ __('Remove map') }}</flux:button>
                @endif
            @endif
        </div>
    </div>

    @if ($mapLots > 0)
        <p class="text-sm text-zinc-600 dark:text-zinc-300">
            {{ number_format($mapLots) }} {{ __('lots') }} · {{ number_format((float) $mapInfo->ha, 2) }} ha {{ __('on the map') }}
            @if ($mapInfo->source) · <span class="ut-sub">{{ $mapInfo->source }}</span>@endif
            @if ($mapUpdated) · <span class="ut-sub">{{ __('updated') }} {{ $mapUpdated }}</span>@endif
        </p>
    @else
        <p class="text-sm text-zinc-500">
            {{ __('No map yet for this IA.') }}
            @if ($canEdit) {{ __('Upload its service-area outline as .kmz, .kml or .geojson (WGS84).') }} @endif
        </p>
    @endif

    @if ($canEdit)
        <script>
        (function () {
            if (window.__iaMapCard) return;
            window.__iaMapCard = true;

            var SWAL = ['https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js', 'sha384-YB/DdIkloKoRpclWB8bNcYXWakt57USgtQPDzvnIDHYU0lasD5eWlXVo1S4ODukY'];
            var loading = null;

            function loadSwal() {
                if (window.Swal) return Promise.resolve(window.Swal);
                loading = loading || new Promise(function (resolve, reject) {
                    var s = document.createElement('script');
                    s.src = SWAL[0]; s.integrity = SWAL[1]; s.crossOrigin = 'anonymous';
                    s.onload = function () { resolve(window.Swal); };
                    s.onerror = function () { loading = null; reject(new Error('swal')); };
                    document.head.appendChild(s);
                });
                return loading;
            }

            function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
            function dark() { return document.documentElement.classList.contains('dark'); }
            function fire(Swal, o) { return Swal.fire(Object.assign({ theme: dark() ? 'dark' : 'light', confirmButtonColor: '#16a34a' }, o)); }
            function csrf() { var p = document.getElementById('ia-page'); return p ? p.dataset.csrf : ''; }

            function send(url, method, form) {
                return fetch(url, {
                    method: method, credentials: 'same-origin', body: form || undefined,
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) {
                    return r.json().catch(function () { return {}; }).then(function (body) { return { ok: r.ok, status: r.status, body: body }; });
                }).catch(function () {
                    return { ok: false, status: 0, body: { message: 'Could not reach the server. Check your connection and try again.' } };
                });
            }

            function errorText(res) {
                var b = res.body || {};
                if (res.status === 422 && b.errors) return Object.keys(b.errors).map(function (k) { return b.errors[k].join(' '); }).join(' ');
                if (res.status === 403) return b.message && !/unauthorized/i.test(b.message) ? b.message : 'You do not have permission to do that.';
                if (res.status === 413) return 'The file is larger than the server allows.';
                if (res.status === 419) return 'Your session expired. Refresh the page and try again.';
                return b.message || 'Please try again.';
            }

            function num(n, d) { return Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: d == null ? 2 : d }); }

            async function upload(card) {
                var Swal = await loadSwal();
                var existing = Number(card.dataset.existing || 0);
                var picked = null, preview = null;

                var html =
                    '<div style="text-align:start;display:grid;gap:12px">' +
                    '<div><label style="font-weight:600;font-size:13px">File (.kmz, .kml, .geojson, max 12 MB)</label>' +
                    '<input id="map-file" type="file" accept=".kmz,.kml,.geojson,.json" class="swal2-file" style="margin:6px 0 0;width:100%"></div>' +
                    '<div><label style="font-weight:600;font-size:13px">Lot status</label>' +
                    '<select id="map-status" class="swal2-select" style="margin:6px 0 0;width:100%">' +
                    '<option value="op">Operational / irrigated</option><option value="non_op">Non-operational</option>' +
                    '<option value="converted">Converted</option><option value="da_area">DA area</option><option value="pnra">PNRA</option></select></div>' +
                    (existing > 0 ?
                        '<div style="font-size:14px"><label style="display:block;margin-bottom:4px"><input type="radio" name="map-mode" value="replace" checked> Replace the current map (' + existing + ' lots)</label>' +
                        '<label style="display:block"><input type="radio" name="map-mode" value="add"> Add to the current map</label></div>' : '') +
                    '<div style="font-size:12px;opacity:.75">Export from Google Earth (Save Place As) or QGIS as WGS84 (EPSG:4326). Draw the service area as polygons; shapefiles must be exported to KML/GeoJSON first.</div>' +
                    '</div>';

                var first = await fire(Swal, {
                    title: 'Upload map file', html: html, showCancelButton: true, reverseButtons: true,
                    confirmButtonText: 'Check file', showLoaderOnConfirm: true,
                    allowOutsideClick: function () { return !Swal.isLoading(); },
                    preConfirm: async function () {
                        var file = document.getElementById('map-file').files[0];
                        if (!file) { Swal.showValidationMessage('Choose a file first.'); return false; }
                        var modeEl = document.querySelector('input[name=map-mode]:checked');
                        picked = { file: file, status: document.getElementById('map-status').value, mode: modeEl ? modeEl.value : 'replace' };
                        var fd = new FormData();
                        fd.append('file', file); fd.append('preview', '1'); fd.append('lot_status', picked.status); fd.append('mode', picked.mode);
                        var res = await send(card.dataset.storeUrl, 'POST', fd);
                        if (!res.ok) { Swal.showValidationMessage(errorText(res)); return false; }
                        preview = res.body;
                        return true;
                    }
                });
                if (!first.isConfirmed || !preview) return;

                var warn = (preview.warnings || []).map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('');
                var sa = Number(card.dataset.serviceArea || 0);
                if (sa > 0 && preview.total_ha > 0 && Math.abs(preview.total_ha - sa) / sa > 0.5) {
                    warn += '<li>The map area (' + num(preview.total_ha) + ' ha) is very different from the IA service area (' + num(sa) + ' ha). Check that this is the right file.</li>';
                }
                var replaceNote = picked.mode === 'replace' && preview.existing_lots > 0
                    ? '<p style="margin-top:10px;color:#b91c1c">The ' + preview.existing_lots + ' current lots will be replaced.</p>' : '';

                var second = await fire(Swal, {
                    icon: warn ? 'warning' : 'question', title: 'Save this map?',
                    html: '<div><strong>' + esc(picked.file.name) + '</strong><br>' + num(preview.lots, 0) + ' lots · ' + num(preview.total_ha) + ' ha' +
                        (warn ? '<ul style="text-align:start;margin-top:10px;font-size:13px;padding-inline-start:18px">' + warn + '</ul>' : '') + replaceNote + '</div>',
                    showCancelButton: true, reverseButtons: true, confirmButtonText: 'Save map', showLoaderOnConfirm: true,
                    allowOutsideClick: function () { return !Swal.isLoading(); },
                    preConfirm: async function () {
                        var fd = new FormData();
                        fd.append('file', picked.file); fd.append('lot_status', picked.status); fd.append('mode', picked.mode);
                        var res = await send(card.dataset.storeUrl, 'POST', fd);
                        if (!res.ok) { Swal.showValidationMessage(errorText(res)); return false; }
                        return res.body;
                    }
                });
                if (second.isConfirmed) {
                    await fire(Swal, { icon: 'success', title: 'Map saved', text: second.value.message, timer: 1800, showConfirmButton: false });
                    window.location.reload();
                }
            }

            async function remove(card) {
                var Swal = await loadSwal();
                var r = await fire(Swal, {
                    icon: 'warning', title: 'Remove the map?',
                    html: 'All ' + esc(card.dataset.existing) + ' lots of <strong>' + esc(card.dataset.name) + '</strong> will be removed from the map. The IA profile itself is not changed.',
                    showCancelButton: true, reverseButtons: true, confirmButtonText: 'Yes, remove', confirmButtonColor: '#dc2626', showLoaderOnConfirm: true,
                    allowOutsideClick: function () { return !Swal.isLoading(); },
                    preConfirm: async function () {
                        var res = await send(card.dataset.destroyUrl, 'DELETE');
                        if (!res.ok) { Swal.showValidationMessage(errorText(res)); return false; }
                        return res.body;
                    }
                });
                if (r.isConfirmed) window.location.reload();
            }

            document.addEventListener('click', function (e) {
                var up = e.target.closest('[data-map-upload]'), rm = e.target.closest('[data-map-remove]');
                if (!up && !rm) return;
                var card = document.getElementById('ia-map-card');
                if (!card) return;
                var run = up ? upload(card) : remove(card);
                run.catch(function () { alert('Could not load the dialog (the SweetAlert library did not load). Check your internet connection and try again.'); });
            });
        })();
        </script>
    @endif
</section>
