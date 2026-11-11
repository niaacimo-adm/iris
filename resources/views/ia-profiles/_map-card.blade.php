{{-- ===== Service area map: summary + upload / replace / remove (ia.edit). Self-contained script, no bundle needed. ===== --}}
@php
    $mapLots = (int) ($mapInfo->lots ?? 0);
    $netItems = (int) ($netInfo->items ?? 0);
    $hasAnyMap = $mapLots > 0 || $netItems > 0;
    $mapUpdated = collect([$mapInfo?->updated, $netInfo?->updated])->filter()->max();
    $mapUpdated = $mapUpdated ? \Illuminate\Support\Carbon::parse($mapUpdated)->format('M d, Y') : null;
@endphp
<section class="ia-card" id="ia-map-card"
         data-store-url="{{ route('ia-profiles.map.store', $profile) }}"
         data-destroy-url="{{ route('ia-profiles.map.destroy', $profile) }}"
         data-existing="{{ $mapLots }}"
         data-existing-net="{{ $netItems }}"
         data-name="{{ $profile->ia_name }}"
         data-service-area="{{ $profile->service_area_ha }}">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h3 class="ia-section-title" style="margin:0">{{ __('Service area map') }}</h3>
        <div class="flex flex-wrap items-center gap-2">
            @if ($hasAnyMap && ! $archived)
                <flux:button size="sm" variant="ghost" icon="globe-asia-australia" :href="route('ia-map.index', ['ia' => $profile->id])">{{ __('View on map') }}</flux:button>
            @endif
            @if ($canEdit)
                <flux:button size="sm" variant="primary" icon="arrow-up-tray" type="button" data-map-upload>{{ $hasAnyMap ? __('Replace / add map file') : __('Upload map file') }}</flux:button>
                @if ($hasAnyMap)
                    <flux:button size="sm" variant="danger" icon="trash" type="button" data-map-remove>{{ __('Remove map') }}</flux:button>
                @endif
            @endif
        </div>
    </div>

    @if ($hasAnyMap)
        <div class="grid gap-1 text-sm text-zinc-600 dark:text-zinc-300">
            @if ($mapLots > 0)
                <p>{{ number_format($mapLots) }} {{ __('lots') }} · {{ number_format((float) $mapInfo->ha, 2) }} ha {{ __('service area on the map') }}
                    @if ($mapInfo->source) · <span class="ut-sub">{{ $mapInfo->source }}</span>@endif</p>
            @endif
            @if ($netItems > 0)
                <p>{{ __('Canal network') }}: {{ number_format((float) $netInfo->canal_m / 1000, 2) }} km {{ __('of canal') }} · {{ number_format($netItems) }} {{ __('items (canals, structures, dams, damages)') }}</p>
            @endif
            @if ($mapUpdated)<p class="ut-sub">{{ __('Updated') }} {{ $mapUpdated }}</p>@endif
        </div>
    @else
        <p class="text-sm text-zinc-500">
            {{ __('No map yet for this IA.') }}
            @if ($canEdit) {{ __('Upload its service-area lots and / or canal network as .kmz, .kml or .geojson (WGS84).') }} @endif
        </p>
    @endif

    @if ($mapLots > 0)
        <details class="mt-3">
            <summary class="cursor-pointer text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('View the lots') }} ({{ number_format($mapLots) }})</summary>
            <div class="mt-2 max-h-96 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-white text-left text-xs uppercase text-zinc-500 dark:bg-zinc-900">
                        <tr><th class="py-1.5 pe-3">{{ __('Lot #') }}</th><th class="pe-3 text-right">{{ __('Area (ha)') }}</th><th class="ps-6">{{ __('Type') }}</th><th>{{ __('Municipality') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($lotRows as $row)
                            <tr class="border-t border-zinc-200 dark:border-white/10">
                                <td class="py-1.5 pe-3">{{ $loop->iteration }}</td>
                                <td class="pe-3 text-right tabular-nums">{{ number_format((float) $row->area_ha, 4) }}</td>
                                <td class="ps-6">{{ \App\Models\IaMapLot::STATUSES[$row->lot_status] ?? $row->lot_status }}</td>
                                <td>{{ $row->municity ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-zinc-300 font-semibold dark:border-white/20">
                            <td class="py-1.5 pe-3">{{ __('Total') }}</td>
                            <td class="pe-3 text-right tabular-nums">{{ number_format((float) $lotRows->sum('area_ha'), 4) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="ut-sub mt-1">{{ __('Lot numbers follow the order of the map file; on the map choose "Labels" to show them.') }}</p>
        </details>
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
                    '<div><label style="font-weight:600;font-size:13px">Lot status (for service-area polygons)</label>' +
                    '<select id="map-status" class="swal2-select" style="margin:6px 0 0;width:100%">' +
                    '<option value="op">Operational / irrigated</option><option value="non_op">Non-operational</option>' +
                    '<option value="converted">Converted</option><option value="da_area">DA area</option><option value="pnra">PNRA</option></select></div>' +
                    (existing > 0 ?
                        '<div style="font-size:14px"><div style="font-weight:600;font-size:13px;margin-bottom:4px">If the file has lots:</div><label style="display:block;margin-bottom:4px"><input type="radio" name="map-mode" value="replace" checked> Replace the current ' + existing + ' lots</label>' +
                        '<label style="display:block"><input type="radio" name="map-mode" value="add"> Add to the current lots</label></div>' : '') +
                    '<div style="font-size:12px;opacity:.75">Export from Google Earth (Save Place As) or QGIS as WGS84 (EPSG:4326). Polygons become service-area lots; lines and points become the canal network (canals, structures, dams, damages) and replace the current network. Shapefiles must be exported to KML/GeoJSON first.</div>' +
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
                if (sa > 0 && preview.lots > 0 && preview.total_ha > 0 && Math.abs(preview.total_ha - sa) / sa > 0.5) {
                    warn += '<li>The map area (' + num(preview.total_ha) + ' ha) is very different from the IA service area (' + num(sa) + ' ha). Check that this is the right file.</li>';
                }
                var notes = '';
                if (preview.lots > 0 && picked.mode === 'replace' && preview.existing_lots > 0) notes += '<p style="margin-top:10px;color:#b91c1c">The ' + preview.existing_lots + ' current lots will be replaced.</p>';
                if (preview.features > 0 && preview.existing_features > 0) notes += '<p style="margin-top:10px;color:#b91c1c">The current canal network (' + preview.existing_features + ' items) will be replaced.</p>';

                var KIND = { canal: 'canal lines', structure: 'structures', dam: 'dams / intakes', damage: 'damage points' };
                var lines = '';
                if (preview.lots > 0) lines += num(preview.lots, 0) + ' lots · ' + num(preview.total_ha) + ' ha<br>';
                if (preview.features > 0) {
                    lines += '<span>Canal network: ' + num(preview.canal_km) + ' km of canal<br><small>' +
                        Object.keys(preview.counts).map(function (k) { return num(preview.counts[k], 0) + ' ' + (KIND[k] || k); }).join(' · ') + '</small></span>';
                }

                var second = await fire(Swal, {
                    icon: warn ? 'warning' : 'question', title: 'Save this map?',
                    html: '<div><strong>' + esc(picked.file.name) + '</strong><br>' + lines +
                        (warn ? '<ul style="text-align:start;margin-top:10px;font-size:13px;padding-inline-start:18px">' + warn + '</ul>' : '') + notes + '</div>',
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
                var lots = Number(card.dataset.existing || 0), net = Number(card.dataset.existingNet || 0);
                var both = lots > 0 && net > 0;
                var what = net > 0 && !both ? 'the canal network (' + net + ' items)' : 'all ' + lots + ' lots';
                var r = await fire(Swal, {
                    icon: 'warning', title: 'Remove from the map?',
                    html: (both ? '<div style="text-align:start;font-size:14px;margin-bottom:8px"><label style="display:block;margin-bottom:4px"><input type="radio" name="rm-part" value="all" checked> Everything (' + lots + ' lots + ' + net + ' network items)</label>' +
                        '<label style="display:block"><input type="radio" name="rm-part" value="network"> Only the canal network</label></div>'
                        : 'This removes ' + what + ' of <strong>' + esc(card.dataset.name) + '</strong>.') +
                        '<div style="font-size:13px;margin-top:6px;opacity:.8">The IA profile itself is not changed.</div>',
                    showCancelButton: true, reverseButtons: true, confirmButtonText: 'Yes, remove', confirmButtonColor: '#dc2626', showLoaderOnConfirm: true,
                    allowOutsideClick: function () { return !Swal.isLoading(); },
                    preConfirm: async function () {
                        var chosen = document.querySelector('input[name=rm-part]:checked');
                        var which = chosen ? chosen.value : (net > 0 && lots === 0 ? 'network' : 'all');
                        var res = await send(card.dataset.destroyUrl + (which === 'network' ? '?part=network' : ''), 'DELETE');
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
