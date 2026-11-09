<x-layouts::app :title="__('Users')">
    <div id="users-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="flex w-full flex-1 flex-col gap-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Users') }}</flux:heading>
                <flux:subheading>{{ __('Create and manage user accounts.') }}</flux:subheading>
            </div>

            @if (auth()->user()->hasPermission('user.manage'))
                <flux:button id="user-new-btn" type="button" variant="primary" icon="plus">
                    {{ __('New user') }}
                </flux:button>
            @endif
        </div>

        <div class="ut-card">
            {{-- Filters --}}
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <select id="filter-role"
                        class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                    <option value="">{{ __('All roles') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>

                <select id="filter-status"
                        class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                    <option value="">{{ __('Any status') }}</option>
                    <option value="Active">{{ __('Active') }}</option>
                    <option value="Inactive">{{ __('Inactive') }}</option>
                </select>
            </div>

            {{-- Skeleton (visible until the table has loaded) --}}
            <div id="users-skeleton" aria-hidden="true">
                <div class="ut-skel-row is-head">
                    @for ($i = 0; $i < 7; $i++)
                        <div class="ut-skel-bar" style="height: 0.75rem; width: 60%"></div>
                    @endfor
                </div>

                @for ($row = 0; $row < 6; $row++)
                    <div class="ut-skel-row">
                        <div>
                            <div class="ut-skel-bar" style="height: 0.9rem; width: 70%"></div>
                            <div class="ut-skel-bar" style="height: 0.65rem; width: 40%; margin-top: 0.4rem"></div>
                        </div>
                        <div class="ut-skel-bar" style="height: 0.9rem; width: 85%"></div>
                        <div class="ut-skel-bar" style="height: 0.9rem; width: 60%"></div>
                        <div class="ut-skel-bar" style="height: 0.9rem; width: 75%"></div>
                        <div class="ut-skel-bar" style="height: 1.25rem; width: 4rem; border-radius: 9999px"></div>
                        <div class="ut-skel-bar" style="height: 0.9rem; width: 70%"></div>
                        <div class="ut-skel-bar" style="height: 1.6rem; width: 6rem; margin-left: auto"></div>
                    </div>
                @endfor
            </div>

            {{-- DataTable --}}
            <div id="users-table-wrap" class="ut-hidden">
                <table id="users-table" class="display" data-url="{{ route('users.data') }}" style="width: 100%">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Office') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Last login') }}</th>
                            <th style="text-align: end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    {{-- Create / Edit modal. The fields are loaded from the existing create/edit pages. --}}
    <flux:modal name="user-form" class="w-full max-w-3xl">
        <form id="user-form"
              method="POST"
              action="{{ route('users.store') }}"
              data-store-url="{{ route('users.store') }}"
              data-create-url="{{ route('users.create') }}"
              autocomplete="off"
              class="flex flex-col gap-6">
            @csrf
            <span id="user-meta"></span>

            <div>
                <flux:heading size="lg" id="user-modal-title">{{ __('New user') }}</flux:heading>
                <flux:subheading id="user-modal-subtitle">{{ __('The account is active immediately. Share the temporary password with the user.') }}</flux:subheading>
            </div>

            <div id="user-fields" class="flex max-h-[60vh] flex-col gap-6 overflow-y-auto pe-1"></div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" id="user-submit">{{ __('Create user') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <script>
        (function () {
            const T = {
                newTitle: @json(__('New user')),
                editTitle: @json(__('Edit user')),
                newSubtitle: @json(__('The account is active immediately. Share the temporary password with the user.')),
                create: @json(__('Create user')),
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
                    if (window.Flux) return resolve(window.Flux.modal('user-form'));
                    const t = setInterval(() => {
                        if (window.Flux) { clearInterval(t); resolve(window.Flux.modal('user-form')); }
                    }, 50);
                });
            }
            const showModal = () => modalApi().then((m) => m.show());
            const closeModal = () => modalApi().then((m) => m.close());

            function clearInlineErrors() {
                el('user-form').querySelectorAll('.js-err').forEach((n) => n.remove());
            }

            function showInlineErrors(errors) {
                const form = el('user-form');
                Object.entries(errors).forEach(([key, msgs]) => {
                    const name = key.replace(/\.\d+$/, '');
                    const field = form.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
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
                    (f) => f.querySelector('[name="email"]') && f.querySelector('[name="first_name"]')
                );
                if (!source) throw new Error('form not found');

                const clone = source.cloneNode(true);
                clone.querySelectorAll('input[name="_token"], input[name="_method"]').forEach((n) => n.remove());
                clone.querySelector('button[type="submit"]')?.closest('div')?.remove();
                el('user-fields').innerHTML = clone.innerHTML;
            }

            async function open(mode, editUrl) {
                const form = el('user-form');
                const isEdit = mode === 'edit';

                clearInlineErrors();
                el('user-fields').innerHTML = '<p class="text-sm text-zinc-500">' + T.loading + '</p>';
                form.action = isEdit ? editUrl.replace(/\/edit\/?$/, '') : form.dataset.storeUrl;
                el('user-meta').innerHTML = isEdit ? '<input type="hidden" name="_method" value="PUT">' : '';
                el('user-modal-title').textContent = isEdit ? T.editTitle : T.newTitle;
                el('user-modal-subtitle').textContent = isEdit ? '' : T.newSubtitle;
                el('user-submit').textContent = isEdit ? T.save : T.create;

                await showModal();
                try {
                    await loadFields(isEdit ? editUrl : form.dataset.createUrl);
                } catch (e) {
                    await closeModal();
                    window.location.href = isEdit ? editUrl : form.dataset.createUrl; // full-page fallback
                }
            }

            async function submit(form) {
                const button = el('user-submit');
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
                        message = doc.getElementById('users-page')?.dataset.status || '';
                    }

                    await closeModal();
                    if (message) showToast('success', message);

                    try {
                        window.jQuery('#users-table').DataTable().ajax.reload(null, false);
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
            if (!window.__usersModalBound) {
                window.__usersModalBound = true;

                document.addEventListener('click', (e) => {
                    if (e.target.closest('#user-new-btn')) {
                        e.preventDefault();
                        open('create');
                        return;
                    }
                    const link = e.target.closest('#users-table a[href$="/edit"]');
                    if (link) {
                        e.preventDefault();
                        open('edit', link.href);
                    }
                });

                document.addEventListener('submit', (e) => {
                    if (e.target && e.target.id === 'user-form') {
                        e.preventDefault();
                        submit(e.target);
                    }
                });
            }
        })();
    </script>
</x-layouts::app>