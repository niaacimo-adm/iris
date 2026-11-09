// IA officers on the profile page (add / edit in a modal, delete with confirmation)
// and the Restore button of an archived profile. All errors are shown with SweetAlert.
import {
    Swal, bindModalClose, closeModal, confirmRequest, esc, markInvalid, openModal,
    sendJson, showErrors, showRequestError, swal, toast,
} from './ia-swal';

let editingUrl = null;

function initOfficers() {
    const modal = document.getElementById('officer-modal');

    if (modal && !modal.dataset.ready) {
        modal.dataset.ready = '1';
        bindModalClose(modal);
        bindOfficerForm(modal);
    }
}

function openOfficerForm(officer = null) {
    const modal = document.getElementById('officer-modal');
    const form = document.getElementById('officer-form');

    editingUrl = officer?.url ?? null;
    form.querySelectorAll('.ia-invalid').forEach((i) => i.classList.remove('ia-invalid'));

    ['officer_name', 'position', 'contact_number', 'email'].forEach((name) => {
        form.elements[name].value = officer?.[name] ?? '';
    });

    document.getElementById('officer-modal-title').textContent = officer ? 'Edit officer' : 'Add officer';
    openModal(modal);
}

function bindOfficerForm(modal) {
    const form = document.getElementById('officer-form');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const data = Object.fromEntries(
            ['officer_name', 'position', 'contact_number', 'email'].map((name) => [name, form.elements[name].value.trim()])
        );

        const problems = {};

        if (data.officer_name === '') {
            problems.officer_name = ['Enter the officer\'s name.'];
        }

        if (data.email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
            problems.email = ['Enter a valid email address, for example name@example.com.'];
        }

        if (data.contact_number !== '' && !/^[0-9+\-\s()\/,]{5,20}$/.test(data.contact_number)) {
            problems.contact_number = ['Use digits and + - ( ) / , only (5 to 20 characters).'];
        }

        if (Object.keys(problems).length) {
            markInvalid(form, problems);
            await showErrors(problems, 'Please check the officer details');
            form.elements[Object.keys(problems)[0]]?.focus();

            return;
        }

        const save = document.getElementById('officer-save');
        save.disabled = true;

        const result = editingUrl
            ? await sendJson(editingUrl, 'PUT', data)
            : await sendJson(form.dataset.storeUrl, 'POST', data);

        save.disabled = false;

        if (!result.ok) {
            if (result.status === 422) {
                markInvalid(form, result.body.errors);
            }

            await showRequestError(result, 'Could not save the officer');

            return;
        }

        // The server flashed the message; reload so the list and the activity history are fresh.
        closeModal(modal);
        window.location.reload();
    });
}

async function deleteOfficer(button) {
    const body = await confirmRequest({
        title: 'Delete this officer?',
        html: `<strong>${esc(button.dataset.name)}</strong> will be removed from the officers of this IA.`,
        confirmText: 'Yes, delete',
        url: button.dataset.url,
        method: 'DELETE',
    });

    if (body) {
        window.location.reload();
    }
}

async function restoreProfile(button) {
    let failed = null;

    const result = await swal({
        icon: 'question',
        title: 'Restore this IA profile?',
        html: `<strong>${esc(button.dataset.name)}</strong> will go back to the IA profiles list.`,
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Yes, restore',
        confirmButtonColor: '#16a34a',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
            const response = await sendJson(button.dataset.url, 'POST');

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
        setTimeout(() => window.location.assign(button.dataset.redirect), 700);
    }
}

function bindGlobal() {
    if (window.__iaOfficerHandlersBound) {
        return;
    }

    window.__iaOfficerHandlersBound = true;

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-officer-add]')) {
            openOfficerForm();

            return;
        }

        const edit = event.target.closest('[data-officer-edit]');

        if (edit) {
            openOfficerForm(JSON.parse(edit.dataset.officer));

            return;
        }

        const del = event.target.closest('[data-officer-delete]');

        if (del) {
            deleteOfficer(del);

            return;
        }

        const restore = event.target.closest('[data-ia-restore-profile]');

        if (restore) {
            restoreProfile(restore);
        }
    });
}

function init() {
    bindGlobal();
    initOfficers();
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
if (document.readyState !== 'loading') init();
