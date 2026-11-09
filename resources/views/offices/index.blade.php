<x-layouts::app :title="__('Offices')">
    <div id="offices-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="flex w-full flex-1 flex-col gap-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Offices') }}</flux:heading>
                <flux:subheading>{{ __('Regional offices and IMOs, and the provinces each one covers.') }}</flux:subheading>
            </div>

            <flux:button id="office-new-btn" type="button" variant="primary" icon="plus">
                {{ __('New office') }}
            </flux:button>
        </div>

        <div class="ut-card">
            {{-- Filters --}}
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <select id="filter-type"
                        class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                    <option value="">{{ __('All types') }}</option>
                    @foreach ($types as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select id="filter-status"
                        class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                    <option value="">{{ __('Any status') }}</option>
                    <option value="Active">{{ __('Active') }}</option>
                    <option value="Inactive">{{ __('Inactive') }}</option>
                </select>
            </div>

            @include('reference._skeleton', ['id' => 'offices-skeleton', 'grid' => 'rt-skel-offices', 'cols' => 8])

            <div id="offices-table-wrap" class="rt-wrap ut-hidden">
                <table id="offices-table" class="display" data-url="{{ route('offices.data') }}" style="width: 100%">
                    <thead>
                        <tr>
                            <th>{{ __('Office') }}</th>
                            <th>{{ __('Region') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Parent') }}</th>
                            <th>{{ __('Provinces') }}</th>
                            <th>{{ __('Users') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th style="text-align: end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    {{-- Create / Edit modal. The fields are loaded from the existing create/edit pages. --}}
    <flux:modal name="office-form" class="w-full max-w-3xl">
        <form id="office-form"
              method="POST"
              action="{{ route('offices.store') }}"
              data-store-url="{{ route('offices.store') }}"
              data-create-url="{{ route('offices.create') }}"
              class="flex flex-col gap-6">
            @csrf
            <span id="office-meta"></span>

            <div>
                <flux:heading size="lg" id="office-modal-title">{{ __('New office') }}</flux:heading>
            </div>

            <div id="office-fields" class="flex flex-col gap-6"></div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" id="office-submit">{{ __('Create office') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <script>
        (function () {
            const T = {
                newTitle: @json(__('New office')),
                editTitle: @json(__('Edit office')),
                create: @json(__('Create office')),
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
                    if (window.Flux) return resolve(window.Flux.modal('office-form'));
                    const t = setInterval(() => {
                        if (window.Flux) { clearInterval(t); resolve(window.Flux.modal('office-form')); }
                    }, 50);
                });
            }
            const showModal = () => modalApi().then((m) => m.show());
            const closeModal = () => modalApi().then((m) => m.close());

            function clearInlineErrors() {
                el('office-form').querySelectorAll('.js-err').forEach((n) => n.remove());
            }

            function showInlineErrors(errors) {
                const form = el('office-form');
                Object.entries(errors).forEach(([key, msgs]) => {
                    const name = key.replace(/\.\d+$/, '');
                    const field = form.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
                    if (!field) return;
                    const p = document.createElement('p');
                    p.className = 'js-err mt-1 text-sm text-red-500';
                    p.textContent = msgs[0];
                    const anchor = field.closest('div') || field;
                    anchor.appendChild(p);
                });
            }

            // Load the form fields from the existing create/edit page
            async function loadFields(pageUrl) {
                const res = await fetch(pageUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const source = [...doc.querySelectorAll('form')].find((f) => f.querySelector('[name="office_type"]'));
                if (!source) throw new Error('form not found');

                const clone = source.cloneNode(true);
                clone.querySelectorAll('input[name="_token"], input[name="_method"]').forEach((n) => n.remove());
                clone.querySelector('button[type="submit"]')?.closest('div')?.remove();
                el('office-fields').innerHTML = clone.innerHTML;
            }

            async function open(mode, editUrl) {
                const form = el('office-form');
                const isEdit = mode === 'edit';

                clearInlineErrors();
                el('office-fields').innerHTML = '<p class="text-sm text-zinc-500">' + T.loading + '</p>';
                form.action = isEdit ? editUrl.replace(/\/edit\/?$/, '') : form.dataset.storeUrl;
                el('office-meta').innerHTML = isEdit ? '<input type="hidden" name="_method" value="PUT">' : '';
                el('office-modal-title').textContent = isEdit ? T.editTitle : T.newTitle;
                el('office-submit').textContent = isEdit ? T.save : T.create;

                await showModal();
                try {
                    await loadFields(isEdit ? editUrl : form.dataset.createUrl);
                } catch (e) {
                    await closeModal();
                    window.location.href = isEdit ? editUrl : form.dataset.createUrl; // full-page fallback
                }
            }

            async function submit(form) {
                const button = el('office-submit');
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
                        message = doc.getElementById('offices-page')?.dataset.status || '';
                    }

                    await closeModal();
                    if (message) showToast('success', message);

                    try {
                        window.jQuery('#offices-table').DataTable().ajax.reload(null, false);
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
            if (!window.__officesModalBound) {
                window.__officesModalBound = true;

                document.addEventListener('click', (e) => {
                    if (e.target.closest('#office-new-btn')) {
                        e.preventDefault();
                        open('create');
                        return;
                    }
                    const link = e.target.closest('#offices-table a[href$="/edit"]');
                    if (link) {
                        e.preventDefault();
                        open('edit', link.href);
                    }
                });

                document.addEventListener('submit', (e) => {
                    if (e.target && e.target.id === 'office-form') {
                        e.preventDefault();
                        submit(e.target);
                    }
                });
            }
        })();
    </script>
</x-layouts::app>