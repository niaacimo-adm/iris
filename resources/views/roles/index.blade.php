<x-layouts::app :title="__('Roles')">
    <div id="roles-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="flex w-full flex-1 flex-col gap-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Roles') }}</flux:heading>
                <flux:subheading>{{ __('Define roles and choose which permissions each one grants.') }}</flux:subheading>
            </div>

            @if (auth()->user()->hasPermission('role.manage') && auth()->user()->isSuperAdmin())
                <flux:button variant="primary" icon="plus" :href="route('roles.create')">
                    {{ __('New role') }}
                </flux:button>
            @endif
        </div>

        <div class="ut-card">
            {{-- Filters --}}
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <select id="filter-scope"
                        class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                    <option value="">{{ __('All data scopes') }}</option>
                    @foreach ($dataScopes as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Skeleton (visible until the table has loaded) --}}
            <div id="roles-skeleton" aria-hidden="true">
                <div class="rt-skel-row rt-skel-roles is-head">
                    @for ($i = 0; $i < 6; $i++)
                        <div class="ut-skel-bar" style="height: 0.75rem; width: 60%"></div>
                    @endfor
                </div>

                @for ($row = 0; $row < 6; $row++)
                    <div class="rt-skel-row rt-skel-roles">
                        <div>
                            <div class="ut-skel-bar" style="height: 0.9rem; width: 70%"></div>
                            <div class="ut-skel-bar" style="height: 0.65rem; width: 40%; margin-top: 0.4rem"></div>
                        </div>
                        <div class="ut-skel-bar" style="height: 1.25rem; width: 5rem; border-radius: 9999px"></div>
                        <div class="ut-skel-bar" style="height: 0.9rem; width: 30%"></div>
                        <div class="ut-skel-bar" style="height: 0.9rem; width: 30%"></div>
                        <div class="ut-skel-bar" style="height: 0.9rem; width: 60%"></div>
                        <div class="ut-skel-bar" style="height: 1.6rem; width: 6rem; margin-left: auto"></div>
                    </div>
                @endfor
            </div>

            {{-- DataTable --}}
            <div id="roles-table-wrap" class="rt-wrap ut-hidden">
                <table id="roles-table" class="display" data-url="{{ route('roles.data') }}" style="width: 100%">
                    <thead>
                        <tr>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Data scope') }}</th>
                            <th>{{ __('Permissions') }}</th>
                            <th>{{ __('Users') }}</th>
                            <th>{{ __('OTP') }}</th>
                            <th style="text-align: end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</x-layouts::app>
