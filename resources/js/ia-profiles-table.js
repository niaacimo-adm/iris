import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import Swal from 'sweetalert2';
import '../css/users-table.css';
import '../css/ia-profiles.css';
import { sendJson, showErrors, showRequestError } from './ia-swal';

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

let iaTable = null;

/* ------------------------------------------------------------------ list */

function renderName(data, type, row) {
    if (type !== 'display') {
        // The sys:XXX tokens make the "system" dropdown filter exact (an IA called "Agos CIS" is not a CIS match).
        return `${row.ia_name} ${row.ia_code ?? ''} ${(row.systems ?? []).map((s) => `sys:${s}`).join(' ')}`;
    }

    const systems = (row.systems ?? []).map((s) => `<span class="ia-sys ia-sys-${s.toLowerCase()}">${s}</span>`).join('');

    return `
        <a href="${esc(row.show_url)}" class="ut-name">${esc(row.ia_name)}</a>
        ${row.ia_code ? `<div class="ut-sub">${esc(row.ia_code)}</div>` : ''}
        ${systems ? `<div class="ia-sys-row">${systems}</div>` : ''}
        ${row.district ? `<div class="ut-sub">${esc(row.district)}</div>` : ''}
    `;
}

function renderStatus(data, type, row) {
    if (type !== 'display') {
        return row.status_label;
    }

    return row.status === 'operational'
        ? `<span class="ut-badge ut-badge-active">${esc(row.status_label)}</span>`
        : `<span class="ut-badge ut-badge-inactive">${esc(row.status_label)}</span>`;
}

function renderArea(data, type, row) {
    if (row.service_area === null || row.service_area === undefined) {
        return type === 'display' ? '' : -1;
    }

    return type === 'display'
        ? row.service_area.toLocaleString(undefined, { maximumFractionDigits: 4 })
        : row.service_area;
}

function renderActions(data, type, row) {
    if (type !== 'display') {
        return '';
    }

    let html = `<a href="${esc(row.show_url)}" class="ut-btn">View</a>`;

    html += `<a href="${esc(row.lipa_url)}" class="ut-btn" title="LIPA records">LIPA (${row.lipa_count ?? 0})</a>`;

    if (row.can_edit) {
        html += `<a href="${esc(row.edit_url)}" class="ut-btn">Edit</a>`;
    }

    if (row.can_delete) {
        html += row.pending_request
            ? `<a href="${esc(row.requests_url)}" class="ut-btn" title="Waiting for approval">Deletion pending</a>`
            : `<button type="button" class="ut-btn ut-btn-danger" data-ia-archive data-mode="${esc(row.delete_mode)}"
                       data-url="${esc(row.delete_url)}" data-request-url="${esc(row.request_url)}"
                       data-name="${esc(row.ia_name)}">Delete</button>`;
    }

    return `<div class="ut-actions">${html}</div>`;
}

function initIaTable() {
    const el = document.getElementById('ia-table');

    if (!el || el.dataset.ready) {
        return;
    }

    el.dataset.ready = '1';

    const skeleton = document.getElementById('ia-skeleton');
    const wrap = document.getElementById('ia-table-wrap');
    const showOffice = el.dataset.showOffice === '1';

    const showSkeleton = () => {
        skeleton?.classList.remove('ut-hidden');
        wrap?.classList.add('ut-hidden');
    };

    const showTable = () => {
        skeleton?.classList.add('ut-hidden');
        wrap?.classList.remove('ut-hidden');
    };

    DataTable.ext.errMode = 'none';

    // Secondary columns are hidden on phones so the table stays readable.
    const desktopOnly = 'max-md:hidden';

    iaTable = new DataTable(el, {
        ajax: { url: el.dataset.url },
        autoWidth: false,
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        columns: [
            { data: 'ia_name', render: renderName },
            { data: 'cis_name', defaultContent: '', className: desktopOnly },
            { data: 'president_name', defaultContent: '', className: desktopOnly },
            { data: 'contact_number', defaultContent: '', className: desktopOnly },
            { data: 'service_area', render: renderArea, className: 'dt-right' },
            { data: 'members', defaultContent: '', className: 'dt-right' },
            { data: 'tsags', defaultContent: '', className: `${desktopOnly} dt-right` },
            { data: 'status', render: renderStatus },
            { data: 'assigned', defaultContent: '', className: desktopOnly },
            { data: 'province', defaultContent: '', className: desktopOnly },
            { data: 'office', defaultContent: '', className: desktopOnly, visible: showOffice },
            { data: null, orderable: false, searchable: false, render: renderActions },
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search IA profiles...',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_ to _END_ of _TOTAL_ IA profiles',
            infoEmpty: 'No IA profiles',
            infoFiltered: '(filtered from _MAX_)',
            emptyTable: 'No IA profiles yet.',
            zeroRecords: 'No matching IA profiles found.',
        },
        initComplete: showTable,
    });

    iaTable.on('preXhr', showSkeleton);
    iaTable.on('xhr', showTable);
    iaTable.on('error', () => {
        showTable();
        toast('error', 'Could not load IA profiles. Please refresh the page.');
    });

    // Dropdown filters (exact match on the column text)
    const applyFilter = (id, columnIndex) => {
        document.getElementById(id)?.addEventListener('change', (event) => {
            const value = event.target.value;
            iaTable.column(columnIndex).search(value ? new RegExp('^' + escRegex(value) + '$') : '').draw();
        });
    };

    document.getElementById('filter-system')?.addEventListener('change', (event) => {
        const value = event.target.value;
        iaTable.column(0).search(value ? `sys:${value}\\b` : '', { regex: true, smart: false }).draw();
    });

    applyFilter('filter-status', 7);
    applyFilter('filter-province', 9);
    applyFilter('filter-office', 10);
}

/* ------------------------------------------------------------------ shared handlers (bound once) */

// IMO users: ask for the deletion. The reason is validated in the dialog (SweetAlert validation message).
async function requestDeletion(button) {
    let failed = null;

    const result = await swal({
        icon: 'warning',
        title: 'Request deletion?',
        html: `<strong>${esc(button.dataset.name)}</strong> can only be deleted after Super Admin or Regional Staff approve. They will be notified.`,
        input: 'textarea',
        inputLabel: 'Reason for deleting this IA profile',
        inputPlaceholder: 'e.g. Duplicate of another IA profile / IA has been dissolved',
        inputAttributes: { maxlength: 500 },
        inputValidator: (value) => (!value || value.trim().length < 10 ? 'Please give a reason (at least 10 characters).' : undefined),
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Send request',
        confirmButtonColor: '#dc2626',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async (reason) => {
            const response = await sendJson(button.dataset.requestUrl, 'POST', { reason: reason.trim() });

            if (response.ok) {
                return response.body;
            }

            if (response.status === 422 && response.body.errors) {
                Swal.showValidationMessage(Object.values(response.body.errors).flat()[0]);

                return false;
            }

            failed = response;

            return false;
        },
    });

    if (failed) {
        await showRequestError(failed, 'Could not send the request');

        return;
    }

    if (result.isConfirmed) {
        toast('success', result.value.message);

        if (button.dataset.redirect) {
            setTimeout(() => window.location.reload(), 900);
        } else {
            iaTable?.ajax.reload(null, false);
        }
    }
}

async function archiveProfile(button) {
    if (button.dataset.mode === 'request') {
        return requestDeletion(button);
    }

    const token = document.getElementById('ia-page')?.dataset.csrf ?? '';
    const name = button.dataset.name;

    const result = await swal({
        icon: 'warning',
        title: 'Delete this IA profile?',
        html: `<strong>${esc(name)}</strong> will be moved to the Archive and removed from the lists. Its history is kept and it can be restored.`,
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Yes, delete',
        confirmButtonColor: '#dc2626',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
            try {
                const response = await fetch(button.dataset.url, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const body = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(body.message || 'Something went wrong. Please try again.');
                }

                return body;
            } catch (error) {
                Swal.showValidationMessage(error.message);
                return false;
            }
        },
    });

    if (!result.isConfirmed) {
        return;
    }

    if (button.dataset.redirect) {
        // On the profile page: go back to the list (the list shows the flash message).
        window.location.assign(button.dataset.redirect);
        return;
    }

    toast('success', result.value?.message ?? 'IA profile archived.');
    iaTable?.ajax.reload(null, false);
}

function bindGlobalHandlers() {
    if (window.__iaHandlersBound) {
        return;
    }

    window.__iaHandlersBound = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-ia-archive]');

        if (button) {
            event.preventDefault();
            archiveProfile(button);
        }
    });

    // <form data-confirm="Are you sure?"> asks first, then submits.
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-confirm]');

        if (!form || form.dataset.confirmed) {
            return;
        }

        event.preventDefault();

        const result = await swal({
            icon: 'warning',
            title: form.dataset.confirm,
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonText: 'Yes, continue',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'Cancel',
        });

        if (result.isConfirmed) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });
}

function showFlash() {
    const page = document.getElementById('ia-page');

    if (!page || page.dataset.flashShown) {
        return;
    }

    page.dataset.flashShown = '1';

    if (page.dataset.status) {
        toast('success', page.dataset.status);
    }

    if (page.dataset.error) {
        toast('error', page.dataset.error);
    }

    // Validation errors from the profile form (server side): list them in a SweetAlert.
    if (page.dataset.errors) {
        try {
            showErrors(JSON.parse(page.dataset.errors), 'Please fix the following');
        } catch (e) {
            // ignore malformed data
        }
    }
}

function init() {
    bindGlobalHandlers();
    initIaTable();
    showFlash();
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
if (document.readyState !== 'loading') init();
