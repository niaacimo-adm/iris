<x-layouts::app :title="__('Permissions')">
    <div id="permissions-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="flex w-full flex-1 flex-col gap-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Permissions') }}</flux:heading>
                <flux:subheading>{{ __('The individual actions that roles can be allowed to perform.') }}</flux:subheading>
            </div>

            @if (auth()->user()->hasPermission('permission.manage') && auth()->user()->isSuperAdmin())
                <flux:button id="permission-new-btn" type="button" variant="primary" icon="plus">
                    {{ __('New permission') }}
                </flux:button>
            @endif
        </div>

        <div class="ut-card">
            {{-- Filters --}}
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <select id="filter-module"
                        class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                    <option value="">{{ __('All modules') }}</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}">{{ $module }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Skeleton (visible until the table has loaded) --}}
            <div id="permissions-skeleton" aria-hidden="true">
                <div class="rt-skel-row rt-skel-permissions is-head">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="ut-skel-bar" style="height: 0.75rem; width: 60%"></div>
                    @endfor
                </div>

                @for ($row = 0; $row < 6; $row++)
                    <div class="rt-skel-row rt-skel-permissions">
                        <div>
                            <div class="ut-skel-bar" style="height: 0.9rem; width: 55%"></div>
                            <div class="ut-skel-bar" style="height: 0.65rem; width: 75%; margin-top: 0.4rem"></div>
                        </div>
                        <div class="ut-skel-bar" style="height: 1.25rem; width: 4.5rem; border-radius: 9999px"></div>
                        <div class="ut-skel-bar" style="height: 0.9rem; width: 30%"></div>
                        <div class="ut-skel-bar" style="height: 1.6rem; width: 6rem; margin-left: auto"></div>
                    </div>
                @endfor
            </div>

            {{-- DataTable --}}
            <div id="permissions-table-wrap" class="rt-wrap ut-hidden">
                <table id="permissions-table" class="display" data-url="{{ route('permissions.data') }}" style="width: 100%">
                    <thead>
                        <tr>
                            <th>{{ __('Permission') }}</th>
                            <th>{{ __('Module') }}</th>
                            <th>{{ __('Roles') }}</th>
                            <th style="text-align: end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    {{-- Create / Edit modal. The fields are loaded from the existing create/edit pages. --}}
    <flux:modal name="permission-form" class="w-full max-w-lg">
        <form id="permission-form"
              method="POST"
              action="{{ route('permissions.store') }}"
              data-store-url="{{ route('permissions.store') }}"
              data-create-url="{{ route('permissions.create') }}"
              class="flex flex-col gap-6">
            @csrf
            <span id="permission-meta"></span>

            <div>
                <flux:heading size="lg" id="permission-modal-title">{{ __('New permission') }}</flux:heading>
                <flux:subheading id="permission-modal-subtitle">{{ __('It is granted to the super admin role automatically. Assign it to other roles from the Roles page.') }}</flux:subheading>
            </div>

            <div id="permission-fields" class="flex flex-col gap-6"></div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" id="permission-submit">{{ __('Create permission') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <script>
        (function () {
            const T = {
                newTitle: @json(__('New permission')),
                editTitle: @json(__('Edit permission')),
                newSubtitle: @json(__('It is granted to the super admin role automatically. Assign it to other roles from the Roles page.')),
                create: @json(__('Create permission')),
                save: @json(__('Save changes')),
                loading: @json(__('Loading...')),
                validation: @json(__('Validation error')),
                failed: @json(__('Something went wrong. Please try again.')),
                ok: @json(__('OK')),
            };

            const el = (id) => document.getElementById(id);
            const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');

            // ---- SweetAlert2 (use the app's copy, or load it from the CDN) ----
            function withSwal(cb) {
                if (window.Swal) return cb(window.Swal);
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                s.onload = () => cb(window.Swal);
                s.onerror = () => cb(null);
                document.head.appendChild(s);
            }

            function swalTheme() {
                const dark = document.documentElement.classList.contains('dark');
                return {
                    confirmButtonColor: '#137a12',
                    background: dark ? '#0b110b' : '#ffffff',
                    color: dark ? '#e8f0e3' : '#364434',
                };
            }

            function showErrors(messages, done) {
                withSwal((Swal) => {
                    if (!Swal) return done && done();
                    const list = messages.map((m) => '<li style="margin:4px 0">' + esc(m) + '</li>').join('');
                    Swal.fire({
                        icon: 'error',
                        title: T.validation,
                        html: '<ul style="text-align:left;margin:0;padding-left:1.25rem">' + list + '</ul>',
                        confirmButtonText: T.ok,
                        ...swalTheme(),
                    }).then(() => done && done());
                });
            }

            function showToast(icon, text) {
                withSwal((Swal) => {
                    if (!Swal) return;
                    Swal.fire({
                        toast: true, position: 'top-end', icon, title: text,
                        showConfirmButton: false, timer: 2500, timerProgressBar: true,
                        ...swalTheme(),
                    });
                });
            }

            // ---- Modal helpers ----
            function modalApi() {
                return new Promise((resolve) => {
                    if (window.Flux) return resolve(window.Flux.modal('permission-form'));
                    const t = setInterval(() => {
                        if (window.Flux) { clearInterval(t); resolve(window.Flux.modal('permission-form')); }
                    }, 50);
                });
            }
            const showModal = () => modalApi().then((m) => m.show());
            const closeModal = () => modalApi().then((m) => m.close());

            function clearInlineErrors() {
                el('permission-form').querySelectorAll('.js-err').forEach((n) => n.remove());
            }

            function showInlineErrors(errors) {
                const form = el('permission-form');
                Object.entries(errors).forEach(([key, msgs]) => {
                    const field = form.querySelector('[name="' + key + '"]');
                    if (!field) return;
                    const p = document.createElement('p');
                    p.className = 'js-err mt-1 text-sm text-red-500';
                    p.textContent = msgs[0];
                    (field.closest('div') || field).appendChild(p);
                });
            }

            // Load the form fields from the existing create/edit page
            async function loadFields(pageUrl) {
                const res = await fetch(pageUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const source = [...doc.querySelectorAll('form')].find(
                    (f) => f.querySelector('[name="code"]') && f.querySelector('[name="description"]')
                );
                if (!source) throw new Error('form not found');

                const clone = source.cloneNode(true);
                clone.querySelectorAll('input[name="_token"], input[name="_method"]').forEach((n) => n.remove());
                clone.querySelector('button[type="submit"]')?.closest('div')?.remove();
                el('permission-fields').innerHTML = clone.innerHTML;
            }

            async function open(mode, editUrl) {
                const form = el('permission-form');
                const isEdit = mode === 'edit';

                clearInlineErrors();
                el('permission-fields').innerHTML = '<p class="text-sm text-zinc-500">' + T.loading + '</p>';
                form.action = isEdit ? editUrl.replace(/\/edit\/?$/, '') : form.dataset.storeUrl;
                el('permission-meta').innerHTML = isEdit ? '<input type="hidden" name="_method" value="PUT">' : '';
                el('permission-modal-title').textContent = isEdit ? T.editTitle : T.newTitle;
                el('permission-modal-subtitle').textContent = isEdit ? '' : T.newSubtitle;
                el('permission-submit').textContent = isEdit ? T.save : T.create;

                await showModal();
                try {
                    await loadFields(isEdit ? editUrl : form.dataset.createUrl);
                } catch (e) {
                    await closeModal();
                    window.location.href = isEdit ? editUrl : form.dataset.createUrl; // full-page fallback
                }
            }

            async function submit(form) {
                const button = el('permission-submit');
                button.disabled = true;
                clearInlineErrors();

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });

                    if (res.status === 422) {
                        const data = await res.json();
                        const errors = data.errors || {};
                        showInlineErrors(errors);
                        await closeModal();
                        showErrors(Object.values(errors).flat(), showModal); // reopen after OK, input is kept
                        return;
                    }

                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    // Success: Laravel redirected back to the index; read the flash message from it
                    let message = '';
                    const type = res.headers.get('content-type') || '';
                    if (type.includes('application/json')) {
                        message = (await res.json()).message || '';
                    } else {
                        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                        message = doc.getElementById('permissions-page')?.dataset.status || '';
                    }

                    await closeModal();
                    if (message) showToast('success', message);

                    try {
                        window.jQuery('#permissions-table').DataTable().ajax.reload(null, false);
                    } catch (e) {
                        setTimeout(() => window.location.reload(), message ? 1200 : 0);
                    }
                } catch (e) {
                    showErrors([T.failed]);
                } finally {
                    button.disabled = false;
                }
            }

            // Bind once (survives wire:navigate)
            if (!window.__permissionsModalBound) {
                window.__permissionsModalBound = true;

                document.addEventListener('click', (e) => {
                    if (e.target.closest('#permission-new-btn')) {
                        e.preventDefault();
                        open('create');
                        return;
                    }
                    const link = e.target.closest('#permissions-table a[href$="/edit"]');
                    if (link) {
                        e.preventDefault();
                        open('edit', link.href);
                    }
                });

                document.addEventListener('submit', (e) => {
                    if (e.target && e.target.id === 'permission-form') {
                        e.preventDefault();
                        submit(e.target);
                    }
                });
            }
        })();
    </script>
</x-layouts::app>