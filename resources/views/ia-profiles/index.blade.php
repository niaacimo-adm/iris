<x-layouts::app :title="__('IA Profiles')">
    <div id="ia-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="flex w-full flex-1 flex-col gap-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('IA Profiles') }}</flux:heading>
                <flux:subheading>{{ __('Irrigators\' Associations and their profiles.') }}</flux:subheading>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button variant="ghost" icon="archive-box" :href="route('ia-profiles.archived')" wire:navigate>
                    {{ __('Archived') }}@if ($archivedCount > 0) ({{ $archivedCount }})@endif
                </flux:button>

                @if (auth()->user()->hasPermission('ia.create'))
                    <flux:button variant="primary" icon="plus" :href="route('ia-profiles.create')">
                        {{ __('New IA profile') }}
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="ut-card">
            {{-- Filters --}}
            <div class="mb-4 flex flex-wrap items-center gap-3">
                @if ($showOffice)
                    <select id="filter-office"
                            class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                        <option value="">{{ __('All offices') }}</option>
                        @foreach ($offices as $office)
                            <option value="{{ $office->code }}">{{ $office->code }}</option>
                        @endforeach
                    </select>
                @endif

                <select id="filter-province"
                        class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                    <option value="">{{ __('All provinces') }}</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province }}">{{ $province }}</option>
                    @endforeach
                </select>

                <select id="filter-system"
                        class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                    <option value="">{{ __('Any system') }}</option>
                    <option value="CIS">{{ __('CIS (communal)') }}</option>
                    <option value="NIS">{{ __('NIS (national)') }}</option>
                    <option value="SPIP">{{ __('SPIP (solar pump)') }}</option>
                </select>

                <select id="filter-status"
                        class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:text-white">
                    <option value="">{{ __('Any status') }}</option>
                    @foreach ($statuses as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Skeleton (visible until the table has loaded) --}}
            <div id="ia-skeleton" aria-hidden="true">
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
            <div id="ia-table-wrap" class="ut-hidden overflow-x-auto">
                <table id="ia-table" class="display" style="width: 100%"
                       data-url="{{ route('ia-profiles.data') }}"
                       data-show-office="{{ $showOffice ? 1 : 0 }}">
                    <thead>
                        <tr>
                            <th>{{ __('IA name') }}</th>
                            <th>{{ __('Name of CIS') }}</th>
                            <th>{{ __('President') }}</th>
                            <th>{{ __('Contact') }}</th>
                            <th>{{ __('Service area (ha)') }}</th>
                            <th>{{ __('Members') }}</th>
                            <th>{{ __('TSAGs') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Assigned to') }}</th>
                            <th>{{ __('Province') }}</th>
                            <th>{{ __('Office') }}</th>
                            <th style="text-align: end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</x-layouts::app>
