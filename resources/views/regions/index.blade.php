<x-layouts::app :title="__('Regions')">
    <div id="regions-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="flex w-full flex-1 flex-col gap-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Regions') }}</flux:heading>
                <flux:subheading>{{ __('Administrative regions that provinces and offices belong to.') }}</flux:subheading>
            </div>

            <flux:button id="region-new-btn" type="button" variant="primary" icon="plus">
                {{ __('New region') }}
            </flux:button>
        </div>

        <div class="ut-card">
            @include('reference._skeleton', ['id' => 'regions-skeleton', 'grid' => 'rt-skel-regions', 'cols' => 4])

            <div id="regions-table-wrap" class="rt-wrap ut-hidden">
                <table id="regions-table" class="display" data-url="{{ route('regions.data') }}" style="width: 100%">
                    <thead>
                        <tr>
                            <th>{{ __('Region') }}</th>
                            <th>{{ __('Provinces') }}</th>
                            <th>{{ __('Offices') }}</th>
                            <th style="text-align: end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    {{-- Create / Edit modal --}}
    <flux:modal name="region-form" class="w-full max-w-lg">
        <form id="region-form"
              method="POST"
              action="{{ route('regions.store') }}"
              data-store-url="{{ route('regions.store') }}"
              data-reopen="{{ $errors->any() ? '1' : '' }}"
              data-edit-url="{{ old('_region_url') }}"
              class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="_method" value="PUT" id="region-method" disabled>
            <input type="hidden" name="_region_url" value="{{ old('_region_url') }}" id="region-url">

            <div>
                <flux:heading size="lg" id="region-modal-title">{{ __('New region') }}</flux:heading>
            </div>

            <div class="grid gap-6">
                <flux:input
                    name="code"
                    :label="__('Code')"
                    :value="old('code')"
                    :description="__('Up to 10 characters: letters, numbers and hyphens, e.g. V.')"
                    required
                />
                <flux:input name="name" :label="__('Name')" :value="old('name')" required />
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" id="region-submit">{{ __('Create region') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <script>
        (function () {
            const T = {
                newTitle: @json(__('New region')),
                editTitle: @json(__('Edit region')),
                create: @json(__('Create region')),
                save: @json(__('Save changes')),
            };

            const el = (id) => document.getElementById(id);

            function setMode(mode, editUrl) {
                const form = el('region-form');
                const isEdit = mode === 'edit';

                form.action = isEdit ? editUrl.replace(/\/edit\/?$/, '') : form.dataset.storeUrl;
                el('region-method').disabled = !isEdit;
                el('region-url').value = isEdit ? editUrl : '';
                el('region-modal-title').textContent = isEdit ? T.editTitle : T.newTitle;
                el('region-submit').textContent = isEdit ? T.save : T.create;
            }

            function setValues(code, name) {
                const form = el('region-form');
                form.querySelector('[name="code"]').value = code ?? '';
                form.querySelector('[name="name"]').value = name ?? '';
            }

            function showModal() {
                const open = () => window.Flux.modal('region-form').show();
                if (window.Flux) return open();
                const t = setInterval(() => { if (window.Flux) { clearInterval(t); open(); } }, 50);
            }

            function openCreate() {
                setMode('create');
                setValues('', '');
                showModal();
            }

            async function openEdit(url) {
                try {
                    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                    const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                    setMode('edit', url);
                    setValues(
                        doc.querySelector('input[name="code"]')?.value,
                        doc.querySelector('input[name="name"]')?.value
                    );
                    showModal();
                } catch (e) {
                    window.location.href = url; // fall back to the full edit page
                }
            }

            // Bind once (survives wire:navigate)
            if (!window.__regionsModalBound) {
                window.__regionsModalBound = true;
                document.addEventListener('click', (e) => {
                    if (e.target.closest('#region-new-btn')) {
                        e.preventDefault();
                        openCreate();
                        return;
                    }
                    const link = e.target.closest('#regions-table a[href$="/edit"]');
                    if (link) {
                        e.preventDefault();
                        openEdit(link.href);
                    }
                });
            }

            // Load SweetAlert2 only if the app doesn't already provide it
            function withSwal(cb) {
                if (window.Swal) return cb(window.Swal);
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                s.onload = () => cb(window.Swal);
                s.onerror = () => cb(null);
                document.head.appendChild(s);
            }

            function showErrors(messages, done) {
                withSwal((Swal) => {
                    if (!Swal) return done();
                    const dark = document.documentElement.classList.contains('dark');
                    const list = messages
                        .map((m) => '<li style="margin:4px 0">' + String(m).replace(/</g, '&lt;') + '</li>')
                        .join('');
                    Swal.fire({
                        icon: 'error',
                        title: @json(__('Validation error')),
                        html: '<ul style="text-align:left;margin:0;padding-left:1.25rem">' + list + '</ul>',
                        confirmButtonText: @json(__('OK')),
                        confirmButtonColor: '#137a12',
                        background: dark ? '#0b110b' : '#ffffff',
                        color: dark ? '#e8f0e3' : '#364434',
                    }).then(done);
                });
            }

            // After a validation error: show SweetAlert, then re-open the modal
            const form = el('region-form');
            if (form && form.dataset.reopen) {
                const errors = @json($errors->all());
                setMode(form.dataset.editUrl ? 'edit' : 'create', form.dataset.editUrl);
                showErrors(errors, showModal);
            }
        })();
    </script>
</x-layouts::app>