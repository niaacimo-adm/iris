import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import Swal from 'sweetalert2';
import '../css/users-table.css';
import '../css/rbac-table.css';
import '../css/reference-table.css';

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

const isDark = () => document.documentElement.classList.contains('dark');

const esc = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[c]);

const escRegex = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const swal = (options) => Swal.fire({ theme: isDark() ? 'dark' : 'light', ...options });

const toast = (icon, title) =>
    swal({
        toast: true,
        position: 'top-end',
        icon,
        title,
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
    });

async function send(method, url, token) {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': token ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(body.message || 'Something went wrong. Please try again.');
    }

    return body;
}

/* Row action buttons. Confirmation text lives in data-* so one click handler serves every table. */

const editLink = (url) => `<a href="${esc(url)}" class="ut-btn">Edit</a>`;

const actionButton = ({ label, variant, url, method, title, name, message, confirm, color }) => `
    <button type="button" class="ut-btn ${variant}" data-action
            data-url="${esc(url)}" data-method="${esc(method)}" data-title="${esc(title)}"
            data-name="${esc(name)}" data-message="${esc(message)}"
            data-confirm="${esc(confirm)}" data-color="${esc(color)}">${esc(label)}</button>`;

const disabledButton = (label, variant, hint) =>
    `<button type="button" class="ut-btn ${variant}" disabled title="${esc(hint ?? '')}">${esc(label)}</button>`;

const actionsCell = (...parts) => `<div class="ut-actions">${parts.join('')}</div>`;

const countPill = (data, type) => (type === 'display' ? `<span class="rt-count">${esc(data)}</span>` : data);

/** Edit + guarded Delete (regions and provinces). */
const deleteActions = (noun) => (data, type, row) => {
    if (type !== 'display') {
        return '';
    }

    return actionsCell(
        editLink(row.edit_url),
        row.can_delete
            ? actionButton({
                  label: 'Delete',
                  variant: 'ut-btn-danger',
                  url: row.delete_url,
                  method: 'DELETE',
                  title: `Delete this ${noun}?`,
                  name: row.name,
                  message: 'will be permanently deleted.',
                  confirm: 'Yes, delete',
                  color: '#dc2626',
              })
            : disabledButton('Delete', 'ut-btn-danger', row.delete_hint)
    );
};

/* ------------------------------------------------------------------ */
/* Generic table bootstrap                                             */
/* ------------------------------------------------------------------ */

/**
 * Expects these ids in the page:  {key}-page, {key}-table, {key}-table-wrap, {key}-skeleton
 */
function initTable({ key, noun, columns, filters = [], order = [[0, 'asc']] }) {
    const el = document.getElementById(`${key}-table`);

    if (!el || el.dataset.ready) {
        return;
    }

    el.dataset.ready = '1';

    const page = document.getElementById(`${key}-page`);
    const skeleton = document.getElementById(`${key}-skeleton`);
    const wrap = document.getElementById(`${key}-table-wrap`);

    const showSkeleton = () => {
        skeleton?.classList.remove('ut-hidden');
        wrap?.classList.add('ut-hidden');
    };

    const showTable = () => {
        skeleton?.classList.add('ut-hidden');
        wrap?.classList.remove('ut-hidden');
    };

    DataTable.ext.errMode = 'none';

    const table = new DataTable(el, {
        ajax: { url: el.dataset.url },
        autoWidth: false,
        order,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        columns,
        language: {
            search: '',
            searchPlaceholder: `Search ${noun}...`,
            lengthMenu: 'Show _MENU_',
            info: `Showing _START_ to _END_ of _TOTAL_ ${noun}`,
            infoEmpty: `No ${noun}`,
            infoFiltered: '(filtered from _MAX_)',
            emptyTable: `No ${noun} yet.`,
            zeroRecords: `No matching ${noun} found.`,
        },
        initComplete: showTable,
    });

    table.on('preXhr', showSkeleton);
    table.on('xhr', showTable);

    table.on('error', () => {
        showTable();
        toast('error', `Could not load ${noun}. Please refresh the page.`);
    });

    // Dropdown filters: [selectId, columnIndex]
    filters.forEach(([selectId, columnIndex]) => {
        const select = document.getElementById(selectId);

        select?.addEventListener('change', () => {
            const value = select.value;
            table.column(columnIndex).search(value ? new RegExp('^' + escRegex(value) + '$') : '').draw();
        });
    });

    // Delete / activate / deactivate with SweetAlert confirmation
    el.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');

        if (!button) {
            return;
        }

        const d = button.dataset;

        const result = await swal({
            icon: 'warning',
            title: d.title,
            html: `<strong>${esc(d.name)}</strong> ${esc(d.message)}`,
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonText: d.confirm,
            confirmButtonColor: d.color,
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            preConfirm: async () => {
                try {
                    return await send(d.method, d.url, page?.dataset.csrf);
                } catch (error) {
                    Swal.showValidationMessage(error.message);
                    return false;
                }
            },
        });

        if (result.isConfirmed) {
            toast('success', result.value?.message ?? 'Done.');
            table.ajax.reload(null, false);
        }
    });

    // Flash messages from create / edit redirects
    if (page?.dataset.status) {
        toast('success', page.dataset.status);
    }

    if (page?.dataset.error) {
        toast('error', page.dataset.error);
    }
}

/* ------------------------------------------------------------------ */
/* Regions                                                             */
/* ------------------------------------------------------------------ */

function initRegionsTable() {
    initTable({
        key: 'regions',
        noun: 'regions',
        columns: [
            {
                data: 'name',
                render: (data, type, row) =>
                    type !== 'display'
                        ? `${row.name} ${row.code}`
                        : `<div class="ut-name">${esc(row.name)}</div><div class="ut-sub rt-code">${esc(row.code)}</div>`,
            },
            { data: 'provinces_count', render: countPill },
            { data: 'offices_count', render: countPill },
            { data: null, orderable: false, searchable: false, render: deleteActions('region') },
        ],
    });
}

/* ------------------------------------------------------------------ */
/* Provinces                                                           */
/* ------------------------------------------------------------------ */

function initProvincesTable() {
    initTable({
        key: 'provinces',
        noun: 'provinces',
        filters: [['filter-region', 1]],
        columns: [
            {
                data: 'name',
                render: (data, type, row) =>
                    type !== 'display'
                        ? `${row.name} ${row.code}`
                        : `<div class="ut-name">${esc(row.name)}</div><div class="ut-sub rt-code">${esc(row.code)}</div>`,
            },
            { data: 'region', defaultContent: '' },
            { data: 'districts_count', render: countPill },
            { data: 'offices_count', render: countPill },
            { data: null, orderable: false, searchable: false, render: deleteActions('province') },
        ],
    });
}

/* ------------------------------------------------------------------ */
/* Offices                                                             */
/* ------------------------------------------------------------------ */

function renderOfficeActions(data, type, row) {
    if (type !== 'display') {
        return '';
    }

    const toggle = row.is_active
        ? row.is_own_office
            ? disabledButton('Deactivate', 'ut-btn-danger', 'You cannot deactivate your own office.')
            : actionButton({
                  label: 'Deactivate',
                  variant: 'ut-btn-danger',
                  url: row.status_url,
                  method: 'PATCH',
                  title: 'Deactivate this office?',
                  name: row.name,
                  message: 'will no longer be available when assigning users.',
                  confirm: 'Yes, deactivate',
                  color: '#dc2626',
              })
        : actionButton({
              label: 'Activate',
              variant: 'ut-btn-success',
              url: row.status_url,
              method: 'PATCH',
              title: 'Activate this office?',
              name: row.name,
              message: 'will be available again when assigning users.',
              confirm: 'Yes, activate',
              color: '#16a34a',
          });

    return actionsCell(editLink(row.edit_url), toggle);
}

function initOfficesTable() {
    initTable({
        key: 'offices',
        noun: 'offices',
        filters: [
            ['filter-type', 2],
            ['filter-status', 6],
        ],
        columns: [
            {
                data: 'name',
                render: (data, type, row) =>
                    type !== 'display'
                        ? `${row.name} ${row.code}`
                        : `<div class="ut-name">${esc(row.name)}</div><div class="ut-sub rt-code">${esc(row.code)}</div>`,
            },
            { data: 'region', defaultContent: '' },
            {
                data: 'type_label',
                render: (data, type, row) =>
                    type !== 'display'
                        ? data
                        : row.type === 'regional'
                          ? `<span class="rt-module">${esc(data)}</span>`
                          : `<span class="ut-badge ut-badge-inactive">${esc(data)}</span>`,
            },
            {
                data: 'parent',
                defaultContent: '',
                render: (data, type) =>
                    type !== 'display' ? data ?? '' : data ? `<span class="rt-code">${esc(data)}</span>` : '<span class="ut-sub">&mdash;</span>',
            },
            {
                data: 'provinces',
                orderable: false,
                render: (data, type) => {
                    const list = Array.isArray(data) ? data : [];

                    if (type !== 'display') {
                        return list.map((p) => `${p.code} ${p.name}`).join(' ');
                    }

                    return list.length
                        ? `<div class="rt-chips">${list.map((p) => `<span class="rt-count" title="${esc(p.name)}">${esc(p.code)}</span>`).join('')}</div>`
                        : '<span class="ut-sub">&mdash;</span>';
                },
            },
            { data: 'users_count', render: countPill },
            {
                data: 'is_active',
                render: (data, type) => {
                    if (type !== 'display') {
                        return data ? 'Active' : 'Inactive';
                    }

                    return data
                        ? '<span class="ut-badge ut-badge-active">Active</span>'
                        : '<span class="ut-badge ut-badge-inactive">Inactive</span>';
                },
            },
            { data: null, orderable: false, searchable: false, render: renderOfficeActions },
        ],
    });
}

/* Office form: regional offices have no parent, so lock that field. */
function initOfficeForm() {
    const type = document.getElementById('office_type');
    const parent = document.getElementById('parent_office_id');

    if (!type || !parent || type.dataset.ready) {
        return;
    }

    type.dataset.ready = '1';

    const sync = () => {
        const regional = type.value === 'regional';

        parent.disabled = regional;

        if (regional) {
            parent.value = '';
        }
    };

    type.addEventListener('change', sync);
    sync();
}

function init() {
    initRegionsTable();
    initProvincesTable();
    initOfficesTable();
    initOfficeForm();
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
