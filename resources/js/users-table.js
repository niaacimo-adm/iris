import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import Swal from 'sweetalert2';
import '../css/users-table.css';

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

function renderName(data, type, row) {
    if (type !== 'display') {
        return `${row.name} ${row.username ?? ''}`;
    }

    return `
        <div class="ut-name">${esc(row.name)}</div>
        ${row.username ? `<div class="ut-sub">@${esc(row.username)}</div>` : ''}
        ${row.position ? `<div class="ut-sub">${esc(row.position)}</div>` : ''}
    `;
}

function renderStatus(data, type, row) {
    if (type !== 'display') {
        return row.is_active ? 'Active' : 'Inactive';
    }

    return row.is_active
        ? '<span class="ut-badge ut-badge-active">Active</span>'
        : '<span class="ut-badge ut-badge-inactive">Inactive</span>';
}

function renderActions(data, type, row) {
    if (type !== 'display') {
        return '';
    }

    if (!row.can_manage) {
        return '<span class="ut-sub">&mdash;</span>';
    }

    let html = `<a href="${esc(row.edit_url)}" class="ut-btn">Edit</a>`;

    if (!row.is_self) {
        html += `
            <button type="button"
                    class="ut-btn ${row.is_active ? 'ut-btn-danger' : 'ut-btn-success'}"
                    data-toggle-status
                    data-url="${esc(row.status_url)}"
                    data-name="${esc(row.name)}"
                    data-active="${row.is_active ? 1 : 0}">
                ${row.is_active ? 'Deactivate' : 'Activate'}
            </button>`;
    }

    return `<div class="ut-actions">${html}</div>`;
}

async function sendToggle(url) {
    const token = document.getElementById('users-page')?.dataset.csrf;

    const response = await fetch(url, {
        method: 'PATCH',
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

function initUsersTable() {
    const el = document.getElementById('users-table');

    if (!el || el.dataset.ready) {
        return;
    }

    el.dataset.ready = '1';

    const page = document.getElementById('users-page');
    const skeleton = document.getElementById('users-skeleton');
    const wrap = document.getElementById('users-table-wrap');
    const roleFilter = document.getElementById('filter-role');
    const statusFilter = document.getElementById('filter-status');

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
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        columns: [
            { data: 'name', render: renderName },
            { data: 'email' },
            { data: 'office', defaultContent: '' },
            { data: 'role', defaultContent: '' },
            { data: 'is_active', render: renderStatus },
            { data: 'last_login', defaultContent: 'Never' },
            { data: null, orderable: false, searchable: false, render: renderActions },
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search users...',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_ to _END_ of _TOTAL_ users',
            infoEmpty: 'No users',
            infoFiltered: '(filtered from _MAX_)',
            emptyTable: 'No users yet.',
            zeroRecords: 'No matching users found.',
        },
        initComplete: showTable,
    });

    table.on('preXhr', showSkeleton);
    table.on('xhr', showTable);

    table.on('error', () => {
        showTable();
        toast('error', 'Could not load users. Please refresh the page.');
    });

    // Role / status dropdown filters
    const applyFilter = (select, columnIndex) => {
        select?.addEventListener('change', () => {
            const value = select.value;
            table.column(columnIndex).search(value ? new RegExp('^' + escRegex(value) + '$') : '').draw();
        });
    };

    applyFilter(roleFilter, 3);
    applyFilter(statusFilter, 4);

    // Activate / deactivate with SweetAlert confirmation
    el.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-toggle-status]');

        if (!button) {
            return;
        }

        const active = button.dataset.active === '1';
        const name = button.dataset.name;

        const result = await swal({
            icon: 'warning',
            title: active ? 'Deactivate this user?' : 'Activate this user?',
            html: active
                ? `<strong>${esc(name)}</strong> will no longer be able to log in.`
                : `<strong>${esc(name)}</strong> will be able to log in again.`,
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonText: active ? 'Yes, deactivate' : 'Yes, activate',
            confirmButtonColor: active ? '#dc2626' : '#16a34a',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            preConfirm: async () => {
                try {
                    return await sendToggle(button.dataset.url);
                } catch (error) {
                    Swal.showValidationMessage(error.message);
                    return false;
                }
            },
        });

        if (result.isConfirmed) {
            toast('success', result.value?.message ?? 'Status updated.');
            table.ajax.reload(null, false);
        }
    });

    // Flash messages from create / edit redirects
    const status = page?.dataset.status;
    const error = page?.dataset.error;

    if (status) {
        toast('success', status);
    }

    if (error) {
        toast('error', error);
    }
}

document.addEventListener('DOMContentLoaded', initUsersTable);
document.addEventListener('livewire:navigated', initUsersTable);
if (document.readyState !== 'loading') initUsersTable();