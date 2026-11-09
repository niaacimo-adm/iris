// LIPA records of one IA profile: table, filters, totals, add / edit modal, delete.
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import '../css/users-table.css';
import '../css/ia-profiles.css';
import {
    bindModalClose, closeModal, confirmRequest, esc, markInvalid, openModal,
    sendJson, showErrors, showRequestError, toast,
} from './ia-swal';

const num = (v, max = 4) => Number(v ?? 0).toLocaleString(undefined, { maximumFractionDigits: max });

const fmtDate = (value) =>
    value
        ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
        : '';

// Fields the form has: name -> input element id is "lipa-<name>"
const FIELDS = [
    'crop_year', 'season', 'sector', 'no', 'lot_no',
    'landowner_first_name', 'landowner_mi', 'landowner_last_name', 'rsbsa_reg_no',
    'service_area_ha', 'irrigated_planted_area_ha', 'variety_inbred', 'variety_hybrid',
    'date_sown', 'date_planted', 'expected_harvest_date', 'crop_insurance', 'remarks',
];

// Checked in the browser before anything is sent. The server repeats every rule (and adds the cross-checks).
const REQUIRED = {
    crop_year: 'Crop year is required.',
    season: 'Choose the season (Wet or Dry).',
    sector: 'Sector is required.',
    landowner_first_name: 'Landowner first name is required.',
    landowner_last_name: 'Landowner last name is required.',
    service_area_ha: 'Service area is required.',
    irrigated_planted_area_ha: 'Irrigated planted area is required.',
};

let table = null;
let editing = null; // the row being edited, or null when adding

function initLipa() {
    const el = document.getElementById('lipa-table');

    if (!el || el.dataset.ready) {
        return;
    }

    el.dataset.ready = '1';

    const canEdit = el.dataset.canEdit === '1';
    const year = document.getElementById('lipa-filter-year');
    const season = document.getElementById('lipa-filter-season');
    const sector = document.getElementById('lipa-filter-sector');
    const desktopOnly = 'max-md:hidden';

    DataTable.ext.errMode = 'none';

    table = new DataTable(el, {
        ajax: {
            url: el.dataset.url,
            // Year / season / sector are filtered by the server, so large IAs stay quick.
            data: (params) => {
                params.year = year?.value || '';
                params.season = season?.value || '';
                params.sector = sector?.value || '';
            },
        },
        autoWidth: false,
        order: [[1, 'desc'], [0, 'asc'], [3, 'asc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        columns: [
            { data: 'sector_no', visible: false, searchable: false },
            {
                data: 'crop_year',
                render: (d, type, row) => (type === 'display' ? `${row.crop_year} · ${esc(row.season_label)}` : `${row.crop_year}${row.season}`),
            },
            { data: 'sector', orderData: [0, 3] },
            { data: 'no', defaultContent: '', className: 'dt-right' },
            { data: 'lot_no', defaultContent: '' },
            {
                data: 'landowner',
                render: (d, type, row) => (type === 'display'
                    ? `<span class="ut-name">${esc(row.landowner) || '—'}</span>${row.source === 'import' ? '' : '<div class="ut-sub">added manually</div>'}`
                    : row.landowner),
            },
            { data: 'service_area_ha', className: 'dt-right', render: (d, type) => (type === 'display' ? num(d) : d) },
            { data: 'irrigated_planted_area_ha', className: 'dt-right', render: (d, type) => (type === 'display' ? num(d) : d) },
            {
                data: null,
                className: desktopOnly,
                render: (d, type, row) => [row.variety_inbred, row.variety_hybrid && `${row.variety_hybrid} (hybrid)`].filter(Boolean).join(' / '),
            },
            { data: 'date_sown', className: desktopOnly, defaultContent: '', render: (d, type) => (type === 'display' ? fmtDate(d) : d ?? '') },
            { data: 'date_planted', className: desktopOnly, defaultContent: '', render: (d, type) => (type === 'display' ? fmtDate(d) : d ?? '') },
            { data: 'expected_harvest_date', className: desktopOnly, defaultContent: '', render: (d, type) => (type === 'display' ? fmtDate(d) : d ?? '') },
            { data: 'rsbsa_reg_no', className: desktopOnly, defaultContent: '' },
            { data: 'remarks', className: desktopOnly, defaultContent: '', render: (d, type) => (type === 'display' ? esc(d) : d ?? '') },
            {
                data: null,
                orderable: false,
                searchable: false,
                visible: canEdit,
                render: (d, type, row) => (type !== 'display' ? '' : `
                    <div class="ut-actions">
                        <button type="button" class="ut-btn" data-lipa-edit="${row.id}">Edit</button>
                        <button type="button" class="ut-btn ut-btn-danger" data-lipa-delete="${row.id}">Delete</button>
                    </div>`),
            },
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search landowner, lot, RSBSA...',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_ to _END_ of _TOTAL_ records',
            infoEmpty: 'No LIPA records',
            infoFiltered: '(filtered from _MAX_)',
            emptyTable: 'No LIPA records for this IA yet.',
            zeroRecords: 'No matching LIPA records found.',
        },
    });

    table.on('error', () => toast('error', 'Could not load the LIPA records. Please refresh the page.'));
    table.on('draw', updateTotals);

    [year, season, sector].forEach((select) => select?.addEventListener('change', () => table.ajax.reload()));

    if (canEdit) {
        initModal(el);
    }
}

/* Totals follow whatever is shown (filters + search), not only the current page. */
function updateTotals() {
    const rows = table.rows({ search: 'applied' }).data().toArray();
    const sum = (key) => rows.reduce((total, row) => total + Number(row[key] || 0), 0);

    document.getElementById('stat-count').textContent = num(rows.length, 0);
    document.getElementById('stat-service').textContent = num(sum('service_area_ha'));
    document.getElementById('stat-planted').textContent = num(sum('irrigated_planted_area_ha'));
}

/* ------------------------------------------------------------------ add / edit */

function formEl() {
    return document.getElementById('lipa-form');
}

function setForm(row) {
    const form = formEl();
    form.querySelectorAll('.ia-invalid').forEach((i) => i.classList.remove('ia-invalid'));

    FIELDS.forEach((name) => {
        const input = form.elements[name];

        if (input) {
            input.value = row?.[name] ?? '';
        }
    });

    if (!row) {
        // Remember the last used period so a whole sector can be keyed in quickly.
        form.elements.crop_year.value = document.getElementById('lipa-filter-year')?.value || new Date().getFullYear();
        form.elements.season.value = document.getElementById('lipa-filter-season')?.value || '';
        form.elements.sector.value = document.getElementById('lipa-filter-sector')?.value || '';
    }
}

function readForm() {
    const form = formEl();

    return Object.fromEntries(FIELDS.map((name) => [name, form.elements[name]?.value ?? '']));
}

function openForm(row = null) {
    editing = row;
    setForm(row);
    document.getElementById('lipa-modal-title').textContent = row ? 'Edit LIPA record' : 'Add LIPA record';
    openModal(document.getElementById('lipa-modal'));
}

function initModal(tableEl) {
    const modal = document.getElementById('lipa-modal');
    const form = formEl();

    bindModalClose(modal);

    document.getElementById('lipa-add')?.addEventListener('click', () => openForm());

    tableEl.addEventListener('click', (event) => {
        const edit = event.target.closest('[data-lipa-edit]');
        const del = event.target.closest('[data-lipa-delete]');
        const id = Number((edit ?? del)?.dataset.lipaEdit ?? (edit ?? del)?.dataset.lipaDelete);

        if (!id) {
            return;
        }

        const row = table.rows().data().toArray().find((r) => r.id === id);

        if (!row) {
            return;
        }

        edit ? openForm(row) : deleteRow(row);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const data = readForm();

        // 1) quick checks in the browser
        const missing = Object.entries(REQUIRED).filter(([name]) => String(data[name]).trim() === '');

        if (missing.length) {
            markInvalid(form, Object.fromEntries(missing.map(([name, msg]) => [name, [msg]])));
            await showErrors(missing.map(([, msg]) => msg), 'Some required fields are empty');
            form.elements[missing[0][0]]?.focus();

            return;
        }

        // 2) the server checks everything again (duplicates, area and date order, formats)
        const save = document.getElementById('lipa-save');
        save.disabled = true;

        const result = editing
            ? await sendJson(editing.update_url, 'PUT', data)
            : await sendJson(document.getElementById('lipa-table').dataset.storeUrl, 'POST', data);

        save.disabled = false;

        if (!result.ok) {
            if (result.status === 422) {
                markInvalid(form, result.body.errors);
            }

            await showRequestError(result, 'Could not save the LIPA record');

            return;
        }

        closeModal(modal);
        toast(result.body.unchanged ? 'info' : 'success', result.body.message);
        table.ajax.reload(null, false);
    });
}

/* ------------------------------------------------------------------ delete */

async function deleteRow(row) {
    const who = row.landowner || 'this landowner';
    const body = await confirmRequest({
        title: 'Delete this LIPA record?',
        html: `<strong>${esc(who)}</strong>${row.lot_no ? ` · lot ${esc(row.lot_no)}` : ''}<br>${row.crop_year} ${esc(row.season_label)} · ${esc(row.sector)}<br><small>This cannot be undone. The profile history keeps a note of it.</small>`,
        confirmText: 'Yes, delete',
        url: row.delete_url,
        method: 'DELETE',
    });

    if (body) {
        toast('success', body.message);
        table.ajax.reload(null, false);
    }
}

document.addEventListener('DOMContentLoaded', initLipa);
document.addEventListener('livewire:navigated', initLipa);
if (document.readyState !== 'loading') initLipa();
