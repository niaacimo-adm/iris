// Shared helpers for the IA pages: SweetAlert wrappers, JSON requests and the small modal.
import Swal from 'sweetalert2';

export { Swal };

export const isDark = () => document.documentElement.classList.contains('dark');

export const esc = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[c]);

export const swal = (options) => Swal.fire({ theme: isDark() ? 'dark' : 'light', ...options });

export const toast = (icon, title) =>
    swal({
        toast: true,
        position: 'top-end',
        icon,
        title,
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
    });

export const csrf = () => document.getElementById('ia-page')?.dataset.csrf ?? '';

/** Turn Laravel's { errors: { field: [msg] } }, a plain object or an array into a flat list of messages. */
export function errorList(errors) {
    if (!errors) {
        return [];
    }

    const list = Array.isArray(errors) ? errors : Object.values(errors).flat();

    return [...new Set(list.map(String))];
}

/** SweetAlert listing validation errors. Returns the promise so callers can wait for "OK". */
export function showErrors(errors, title = 'Please check the form') {
    const list = errorList(errors);

    if (list.length === 0) {
        return Promise.resolve();
    }

    return swal({
        icon: 'error',
        title,
        html: `<ul class="ia-error-list">${list.map((m) => `<li>${esc(m)}</li>`).join('')}</ul>`,
        confirmButtonText: 'OK, let me fix it',
        confirmButtonColor: '#16a34a',
    });
}

/**
 * Send JSON and return { ok, status, body }. Never throws: network problems come back as ok=false.
 * 422 bodies carry { message, errors }.
 */
export async function sendJson(url, method, payload = null) {
    try {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload === null ? undefined : JSON.stringify(payload),
        });

        const body = await response.json().catch(() => ({}));

        return { ok: response.ok, status: response.status, body };
    } catch (error) {
        return { ok: false, status: 0, body: { message: 'Could not reach the server. Check your connection and try again.' } };
    }
}

/** Show whatever went wrong with a request: validation list for 422, a plain message otherwise. */
export function showRequestError(result, fallbackTitle = 'Something went wrong') {
    const { status, body } = result;

    if (status === 422 && body.errors) {
        return showErrors(body.errors, 'Please fix the following');
    }

    const message =
        status === 403 ? (body.message && !/unauthorized/i.test(body.message) ? body.message : 'You do not have permission to do that.')
        : status === 404 ? 'That record no longer exists. Refresh the page.'
        : status === 419 ? 'Your session expired. Refresh the page and try again.'
        : body.message || 'Please try again.';

    return swal({ icon: 'error', title: fallbackTitle, text: message, confirmButtonColor: '#16a34a' });
}

/** Ask, then run a request inside the dialog (loader on the button). Resolves to the response body or null. */
export async function confirmRequest({ title, html, confirmText, url, method }) {
    let failed = null;

    const result = await swal({
        icon: 'warning',
        title,
        html,
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: confirmText,
        confirmButtonColor: '#dc2626',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
            const response = await sendJson(url, method);

            if (!response.ok) {
                failed = response;

                return false;
            }

            return response.body;
        },
    });

    if (failed) {
        await showRequestError(failed);

        return null;
    }

    return result.isConfirmed ? result.value : null;
}

/* ------------------------------------------------------------------ modal */

export function openModal(modal) {
    modal.hidden = false;
    document.body.classList.add('ia-modal-open');
    modal.querySelector('input:not([type=hidden]), select, textarea')?.focus();
}

export function closeModal(modal) {
    modal.hidden = true;
    document.body.classList.remove('ia-modal-open');
}

/** Close on the backdrop, on [data-modal-close] buttons and on Escape (unless a SweetAlert is open). */
export function bindModalClose(modal) {
    modal.addEventListener('click', (event) => {
        if (event.target === modal || event.target.closest('[data-modal-close]')) {
            closeModal(modal);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden && !Swal.isVisible()) {
            closeModal(modal);
        }
    });
}

/** Red outline on the inputs the server complained about; the first one gets focus. */
export function markInvalid(form, errors) {
    form.querySelectorAll('.ia-invalid').forEach((el) => el.classList.remove('ia-invalid'));

    const names = errors && !Array.isArray(errors) ? Object.keys(errors) : [];
    let first = null;

    names.forEach((name) => {
        const input = form.querySelector(`[name="${name}"]`);

        if (input) {
            input.classList.add('ia-invalid');
            first ??= input;
        }
    });

    first?.focus();
}
