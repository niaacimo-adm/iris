// The archive page: list of archived IA profiles with Restore.
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import '../css/users-table.css';
import '../css/ia-profiles.css';
import { Swal, esc, sendJson, showRequestError, swal, toast } from './ia-swal';

let archiveTable = null;

const fmtDateTime = (value) =>
    value
        ? new Date(value.replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
        : '';

function initArchive() {
    const el = document.getElementById('archive-table');

    if (!el || el.dataset.ready) {
        return;
    }

    el.dataset.ready = '1';
    DataTable.ext.errMode = 'none';

    const desktopOnly = 'max-md:hidden';

    archiveTable = new DataTable(el, {
        ajax: { url: el.dataset.url },
        autoWidth: false,
        order: [[4, 'desc']],
        pageLength: 10,
        columns: [
            {
                data: 'ia_name',
                render: (d, type, row) => (type !== 'display' ? `${row.ia_name} ${row.ia_code ?? ''}` : `
                    <a href="${esc(row.show_url)}" class="ut-name">${esc(row.ia_name)}</a>
                    ${row.ia_code ? `<div class="ut-sub">${esc(row.ia_code)}</div>` : ''}`),
            },
            { data: 'province', defaultContent: '', className: desktopOnly },
            { data: 'office', defaultContent: '', className: desktopOnly, visible: el.dataset.showOffice === '1' },
            { data: 'lipa_count', className: `${desktopOnly} dt-right` },
            { data: 'archived_at', defaultContent: '', render: (d, type) => (type === 'display' ? fmtDateTime(d) : d ?? '') },
            { data: 'archived_by', defaultContent: '', className: desktopOnly },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (d, type, row) => (type !== 'display' ? '' : `
                    <div class="ut-actions">
                        <a href="${esc(row.show_url)}" class="ut-btn">View</a>
                        ${row.can_restore ? `<button type="button" class="ut-btn" data-ia-restore="${row.id}">Restore</button>` : ''}
                    </div>`),
            },
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search archived profiles...',
            info: 'Showing _START_ to _END_ of _TOTAL_ archived profiles',
            infoEmpty: 'Nothing archived',
            emptyTable: 'The archive is empty. Archived IA profiles will appear here.',
            zeroRecords: 'No matching archived profiles.',
        },
    });

    archiveTable.on('error', () => toast('error', 'Could not load the archive. Please refresh the page.'));

    el.addEventListener('click', (event) => {
        const button = event.target.closest('[data-ia-restore]');

        if (!button) {
            return;
        }

        const row = archiveTable.rows().data().toArray().find((r) => r.id === Number(button.dataset.iaRestore));

        if (row) {
            restore(row);
        }
    });
}

async function restore(row) {
    let failed = null;

    const result = await swal({
        icon: 'question',
        title: 'Restore this IA profile?',
        html: `<strong>${esc(row.ia_name)}</strong> will go back to the IA profiles list, with its officers, LIPA records and history.`,
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Yes, restore',
        confirmButtonColor: '#16a34a',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
            const response = await sendJson(row.restore_url, 'POST');

            if (!response.ok) {
                failed = response;

                return false;
            }

            return response.body;
        },
    });

    if (failed) {
        await showRequestError(failed, 'Could not restore');

        return;
    }

    if (result.isConfirmed) {
        toast('success', result.value.message);
        archiveTable.ajax.reload(null, false);
    }
}

document.addEventListener('DOMContentLoaded', initArchive);
document.addEventListener('livewire:navigated', initArchive);
if (document.readyState !== 'loading') initArchive();
