<x-layouts::app :title="__('Archived IA profiles')">
    <div id="ia-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="flex w-full flex-1 flex-col gap-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Archived IA profiles') }}</flux:heading>
                <flux:subheading>{{ __('Profiles that were archived. Nothing here is deleted: open one to read it, or restore it to bring it back to the list.') }}</flux:subheading>
            </div>

            <flux:button variant="ghost" icon="arrow-left" :href="route('ia-profiles.index')" wire:navigate>{{ __('Back to IA profiles') }}</flux:button>
        </div>

        <div class="ut-card">
            <div class="overflow-x-auto">
                <table id="archive-table" class="display" style="width: 100%"
                       data-url="{{ route('ia-profiles.archived.data') }}"
                       data-show-office="{{ $showOffice ? 1 : 0 }}">
                    <thead>
                        <tr>
                            <th>{{ __('IA name') }}</th>
                            <th>{{ __('Province') }}</th>
                            <th>{{ __('Office') }}</th>
                            <th>{{ __('LIPA records') }}</th>
                            <th>{{ __('Archived on') }}</th>
                            <th>{{ __('Archived by') }}</th>
                            <th style="text-align: end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</x-layouts::app>
