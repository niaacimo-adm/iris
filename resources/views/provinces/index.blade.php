@php
    $selectClass = 'w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs dark:border-white/10 dark:bg-zinc-900 dark:text-white';
@endphp

<x-layouts::app :title="__('Provinces')">
    <div id="provinces-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="flex w-full flex-1 flex-col gap-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Provinces') }}</flux:heading>
                <flux:subheading>{{ __('Provinces of each region, used by districts, offices and IA profiles.') }}</flux:subheading>
            </div>

            <flux:button id="province-new-btn" type="button" variant="primary" icon="plus">
                {{ __('New province') }}
            </flux:button>
        </div>

        <div class="ut-card">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <select id="filter-region"
                        class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                    <option value="">{{ __('All regions') }}</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->name }}">{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>

            @include('reference._skeleton', ['id' => 'provinces-skeleton', 'grid' => 'rt-skel-provinces', 'cols' => 5])

            <div id="provinces-table-wrap" class="rt-wrap ut-hidden">
                <table id="provinces-table" class="display" data-url="{{ route('provinces.data') }}" style="width: 100%">
                    <thead>
                        <tr>
                            <th>{{ __('Province') }}</th>
                            <th>{{ __('Region') }}</th>
                            <th>{{ __('Districts') }}</th>
                            <th>{{ __('Offices') }}</th>
                            <th style="text-align: end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    {{-- Create / Edit modal --}}
    <flux:modal name="province-form" class="w-full max-w-lg">
        <form id="province-form"
              method="POST"
              action="{{ route('provinces.store') }}"
              data-store-url="{{ route('provinces.store') }}"
              data-reopen="{{ $errors->any() ? '1' : '' }}"
              data-edit-url="{{ old('_province_url') }}"
              class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="_method" value="PUT" id="province-method" disabled>
            <input type="hidden" name="_province_url" value="{{ old('_province_url') }}" id="province-url">

            <div>
                <flux:heading size="lg" id="province-modal-title">{{ __('New province') }}</flux:heading>
            </div>

            <div class="grid gap-6">
                <div>
                    <label for="province_region_id" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-white">{{ __('Region') }}</label>
                    <select id="province_region_id" name="region_id" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select region') }}</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}" @selected((int) old('region_id') === $region->id)>
                                {{ $region->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('region_id')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input
                    name="code"
                    :label="__('Code')"
                    :value="old('code')"
                    :description="__('Up to 10 characters: letters, numbers and hyphens, e.g. ALB.')"
                    required
                />
                <flux:input name="name" :label="__('Name')" :value="old('name')" required />
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" id="province-submit">{{ __('Create province') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <script>
        (function () {
            const T = {
                newTitle: @json(__('New province')),
                editTitle: @json(__('Edit province')),
                create: @json(__('Create province')),
                save: @json(__('Save changes')),
            };

            const el = (id) => document.getElementById(id);

            function setMode(mode, editUrl) {
                const form = el('province-form');
                const isEdit = mode === 'edit';

                form.action = isEdit ? editUrl.replace(/\/edit\/?$/, '') : form.dataset.storeUrl;
                el('province-method').disabled = !isEdit;
                el('province-url').value = isEdit ? editUrl : '';
                el('province-modal-title').textContent = isEdit ? T.editTitle : T.newTitle;
                el('province-submit').textContent = isEdit ? T.save : T.create;
            }

            function setValues(regionId, code, name) {
                const form = el('province-form');
                const select = form.querySelector('[name="region_id"]');

                // On create, preselect the region when there is only one
                if (regionId === null) {
                    const options = [...select.options].filter((o) => o.value);
                    select.value = options.length === 1 ? options[0].value : '';
                } else {
                    select.value = regionId ?? '';
                }

                form.querySelector('[name="code"]').value = code ?? '';
                form.querySelector('[name="name"]').value = name ?? '';
            }

            function showModal() {
                const open = () => window.Flux.modal('province-form').show();
                if (window.Flux) return open();
                const t = setInterval(() => { if (window.Flux) { clearInterval(t); open(); } }, 50);
            }

            function openCreate() {
                setMode('create');
                setValues(null, '', '');
                showModal();
            }

            async function openEdit(url) {
                try {
                    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                    const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                    setMode('edit', url);
                    setValues(
                        doc.querySelector('select[name="region_id"] option[selected]')?.value ?? '',
                        doc.querySelector('input[name="code"]')?.value,
                        doc.querySelector('input[name="name"]')?.value
                    );
                    showModal();
                } catch (e) {
                    window.location.href = url; // fall back to the full edit page
                }
            }

            // Bind once (survives wire:navigate)
            if (!window.__provincesModalBound) {
                window.__provincesModalBound = true;
                document.addEventListener('click', (e) => {
                    if (e.target.closest('#province-new-btn')) {
                        e.preventDefault();
                        openCreate();
                        return;
                    }
                    const link = e.target.closest('#provinces-table a[href$="/edit"]');
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
            const form = el('province-form');
            if (form && form.dataset.reopen) {
                const errors = @json($errors->all());
                setMode(form.dataset.editUrl ? 'edit' : 'create', form.dataset.editUrl);
                showErrors(errors, showModal);
            }
        })();
    </script>
</x-layouts::app>