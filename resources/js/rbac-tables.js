import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import Swal from 'sweetalert2';
import '../css/users-table.css';
import '../css/rbac-table.css';

/* ------------------------------------------------------------------ */
/* Helpers (same behaviour as users-table.js)                          */
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

async function sendDelete(url, token) {
    const response = await fetch(url, {
        method: 'DELETE',
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

/** Edit + Delete buttons shared by both tables. */
function renderActions(deleteLabel, describe) {
    return (data, type, row) => {
        if (type !== 'display') {
            return '';
        }

        if (!row.can_manage) {
            return '<span class="ut-sub">&mdash;</span>';
        }

        const deleteButton = row.can_delete
            ? `<button type="button" class="ut-btn ut-btn-danger" data-delete
                       data-url="${esc(row.delete_url)}"
                       data-name="${esc(row.name ?? row.code)}"
                       data-message="${esc(describe(row))}">${deleteLabel}</button>`
            : `<button type="button" class="ut-btn ut-btn-danger" disabled
                       title="${esc(row.delete_hint ?? '')}">${deleteLabel}</button>`;

        return `<div class="ut-actions">
            <a href="${esc(row.edit_url)}" class="ut-btn">Edit</a>
            ${deleteButton}
        </div>`;
    };
}

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

    // Delete with SweetAlert confirmation
    el.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete]');

        if (!button) {
            return;
        }

        const result = await swal({
            icon: 'warning',
            title: `Delete this ${noun.replace(/s$/, '')}?`,
            html: `<strong>${esc(button.dataset.name)}</strong> ${esc(button.dataset.message)}`,
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            preConfirm: async () => {
                try {
                    return await sendDelete(button.dataset.url, page?.dataset.csrf);
                } catch (error) {
                    Swal.showValidationMessage(error.message);
                    return false;
                }
            },
        });

        if (result.isConfirmed) {
            toast('success', result.value?.message ?? 'Deleted.');
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
/* Roles table                                                         */
/* ------------------------------------------------------------------ */

function initRolesTable() {
    initTable({
        key: 'roles',
        noun: 'roles',
        filters: [['filter-scope', 1]],
        columns: [
            {
                data: 'name',
                render: (data, type, row) => {
                    if (type !== 'display') {
                        return `${row.name} ${row.code}`;
                    }

                    return `
                        <div class="ut-name">${esc(row.name)}</div>
                        <div class="ut-sub rt-code">${esc(row.code)}</div>
                        ${row.description ? `<div class="ut-sub">${esc(row.description)}</div>` : ''}
                    `;
                },
            },
            {
                data: 'data_scope_label',
                render: (data, type) =>
                    type === 'display' ? `<span class="ut-badge ut-badge-inactive">${esc(data)}</span>` : data,
            },
            {
                data: 'permissions_count',
                render: (data, type) => (type === 'display' ? `<span class="rt-count">${esc(data)}</span>` : data),
            },
            {
                data: 'users_count',
                render: (data, type) => (type === 'display' ? `<span class="rt-count">${esc(data)}</span>` : data),
            },
            {
                data: 'require_otp',
                render: (data, type) => {
                    if (type !== 'display') {
                        return data ? 'Every login' : 'Standard';
                    }

                    return data
                        ? '<span class="ut-badge ut-badge-active">Every login</span>'
                        : '<span class="ut-sub">Standard</span>';
                },
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: renderActions('Delete', () => 'will be permanently deleted. Its permission assignments are removed too.'),
            },
        ],
    });
}

/* ------------------------------------------------------------------ */
/* Permissions table                                                   */
/* ------------------------------------------------------------------ */

function initPermissionsTable() {
    initTable({
        key: 'permissions',
        noun: 'permissions',
        filters: [['filter-module', 1]],
        columns: [
            {
                data: 'code',
                render: (data, type, row) => {
                    if (type !== 'display') {
                        return `${row.code} ${row.description ?? ''}`;
                    }

                    return `
                        <div class="ut-name rt-code">${esc(row.code)}${row.is_builtin ? ' <span class="ut-badge ut-badge-inactive">Built-in</span>' : ''}</div>
                        ${row.description ? `<div class="ut-sub">${esc(row.description)}</div>` : ''}
                    `;
                },
            },
            {
                data: 'module',
                render: (data, type) => (type === 'display' ? `<span class="rt-module">${esc(data)}</span>` : data),
            },
            {
                data: 'roles_count',
                render: (data, type) => (type === 'display' ? `<span class="rt-count">${esc(data)}</span>` : data),
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: renderActions('Delete', (row) =>
                    row.roles_count > 0
                        ? `will be deleted and removed from ${row.roles_count} role${row.roles_count === 1 ? '' : 's'}.`
                        : 'will be permanently deleted.'),
            },
        ],
    });
}

/* ------------------------------------------------------------------ */
/* Role form: "select all" per permission group                        */
/* ------------------------------------------------------------------ */

function initPermissionPicker() {
    document.querySelectorAll('[data-perm-group]').forEach((group) => {
        if (group.dataset.ready) {
            return;
        }

        group.dataset.ready = '1';

        const toggle = group.querySelector('[data-group-toggle]');
        const boxes = [...group.querySelectorAll('input[type="checkbox"][name="permissions[]"]')];

        if (!toggle) {
            return;
        }

        const sync = () => {
            const enabled = boxes.filter((box) => !box.disabled);
            const checked = enabled.filter((box) => box.checked).length;

            toggle.checked = enabled.length > 0 ? checked === enabled.length : boxes.every((box) => box.checked);
            toggle.indeterminate = checked > 0 && checked < enabled.length;
        };

        toggle.addEventListener('change', () => {
            boxes.filter((box) => !box.disabled).forEach((box) => {
                box.checked = toggle.checked;
            });

            sync();
        });

        boxes.forEach((box) => box.addEventListener('change', sync));

        sync();
    });
}

function init() {
    initRolesTable();
    initPermissionsTable();
    initPermissionPicker();
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
