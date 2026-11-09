// Deletion requests page: approve / reject (Super Admin, Regional Staff) and withdraw (requester).
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import '../css/users-table.css';
import '../css/ia-profiles.css';
import { Swal, esc, sendJson, showRequestError, swal, toast } from './ia-swal';

let table = null;

const fmt = (value) =>
    value
        ? new Date(value.replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
        : '';

const badge = (row) => {
    const cls = { pending: 'ia-sys-spip', approved: 'ia-sys-cis', rejected: 'ia-req-rejected', withdrawn: 'ia-req-withdrawn' }[row.status] ?? '';
    const isNew = row.is_new ? ' <span class="ia-sys ia-req-new">NEW</span>' : '';
    const note = row.status === 'pending' || row.status === 'withdrawn' ? ''
        : `<div class="ut-sub">${esc(row.decided_by ?? '')} · ${fmt(row.decided_at)}${row.decision_note ? `<br>“${esc(row.decision_note)}”` : ''}</div>`;

    return `<span class="ia-sys ${cls}">${esc(row.status_label)}</span>${isNew}${note}`;
};

function initDeletion() {
    const el = document.getElementById('deletion-table');

    if (!el || el.dataset.ready) {
        return;
    }

    el.dataset.ready = '1';
    DataTable.ext.errMode = 'none';

    table = new DataTable(el, {
        ajax: { url: el.dataset.url },
        autoWidth: false,
        ordering: false, // the server already puts pending first, newest first
        pageLength: 10,
        columns: [
            {
                data: 'ia_name',
                render: (d, type, row) => (type !== 'display' ? `${row.ia_name} ${row.ia_code ?? ''}` : `
                    ${row.show_url ? `<a href="${esc(row.show_url)}" class="ut-name">${esc(row.ia_name)}</a>` : esc(row.ia_name)}
                    <div class="ut-sub">${esc(row.province ?? '')}${row.ia_code ? ` · ${esc(row.ia_code)}` : ''}</div>`),
            },
            { data: 'office', defaultContent: '', className: 'max-md:hidden' },
            { data: 'requested_by', defaultContent: '', className: 'max-md:hidden' },
            { data: 'reason', render: (d, type) => (type === 'display' ? `<div style="max-width:22rem;white-space:normal">${esc(d)}</div>` : d) },
            { data: 'status', render: (d, type, row) => (type === 'display' ? badge(row) : row.status_label) },
            { data: 'requested_at', className: 'max-md:hidden', render: (d, type) => (type === 'display' ? fmt(d) : d) },
            {
                data: null,
                render: (d, type, row) => (type !== 'display' ? '' : `
                    <div class="ut-actions">
                        ${row.can_decide ? `<button type="button" class="ut-btn" data-dr-approve="${row.id}">Approve</button>
                                            <button type="button" class="ut-btn ut-btn-danger" data-dr-reject="${row.id}">Reject</button>` : ''}
                        ${row.can_withdraw ? `<button type="button" class="ut-btn" data-dr-withdraw="${row.id}">Withdraw</button>` : ''}
                    </div>`),
            },
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search requests...',
            info: 'Showing _START_ to _END_ of _TOTAL_ requests',
            infoEmpty: 'No requests',
            emptyTable: 'No deletion requests.',
            zeroRecords: 'No matching requests.',
        },
    });

    table.on('error', () => toast('error', 'Could not load the requests. Please refresh the page.'));

    el.addEventListener('click', (event) => {
        const find = (attr) => {
            const button = event.target.closest(`[${attr}]`);

            return button ? table.rows().data().toArray().find((r) => r.id === Number(button.getAttribute(attr))) : null;
        };

        const approve = find('data-dr-approve');
        const reject = find('data-dr-reject');
        const withdraw = find('data-dr-withdraw');

        if (approve) decide(approve, 'approve');
        else if (reject) decide(reject, 'reject');
        else if (withdraw) withdrawRequest(withdraw);
    });
}

/** Run a request inside the SweetAlert so the loader shows; validation problems stay in the dialog. */
async function run(row, url, payload, dialog) {
    let failed = null;

    const result = await swal({
        ...dialog,
        showCancelButton: true,
        reverseButtons: true,
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async (input) => {
            const response = await sendJson(url, 'POST', payload(input));

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
        await showRequestError(failed);
        table.ajax.reload(null, false); // someone else may have decided it already

        return;
    }

    if (result.isConfirmed) {
        toast('success', result.value.message);
        table.ajax.reload(null, false);
    }
}

function decide(row, action) {
    const info = `<strong>${esc(row.ia_name)}</strong><br><small>Requested by ${esc(row.requested_by ?? 'a user')}: “${esc(row.reason)}”</small>`;

    if (action === 'approve') {
        return run(row, row.approve_url, (note) => ({ note }), {
            icon: 'warning',
            title: 'Approve deletion?',
            html: `${info}<br><br>The IA profile will be archived (it can be restored later).`,
            input: 'text',
            inputPlaceholder: 'Note to the requester (optional)',
            inputAttributes: { maxlength: 500 },
            confirmButtonText: 'Approve and archive',
            confirmButtonColor: '#dc2626',
        });
    }

    return run(row, row.reject_url, (note) => ({ note }), {
        icon: 'question',
        title: 'Reject deletion?',
        html: info,
        input: 'textarea',
        inputLabel: 'Reason for rejecting (the requester will see this)',
        inputAttributes: { maxlength: 500 },
        inputValidator: (value) => (!value || value.trim().length < 5 ? 'Please give a reason (at least 5 characters).' : undefined),
        confirmButtonText: 'Reject request',
        confirmButtonColor: '#16a34a',
    });
}

function withdrawRequest(row) {
    return run(row, row.withdraw_url, () => ({}), {
        icon: 'question',
        title: 'Withdraw this request?',
        html: `The deletion request for <strong>${esc(row.ia_name)}</strong> will be cancelled.`,
        confirmButtonText: 'Yes, withdraw',
        confirmButtonColor: '#16a34a',
    });
}

document.addEventListener('DOMContentLoaded', initDeletion);
document.addEventListener('livewire:navigated', initDeletion);
if (document.readyState !== 'loading') initDeletion();
