<x-layouts::app :title="__('IA profile deletion requests')">
    <div id="ia-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="flex w-full flex-1 flex-col gap-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Deletion requests') }}</flux:heading>
                <flux:subheading>
                    @if ($isApprover)
                        {{ __('IMO users cannot delete an IA profile on their own. Approve to archive it, or reject with a reason.') }}
                    @else
                        {{ __('Your requests to delete IA profiles, and what Super Admin / Regional Staff decided.') }}
                    @endif
                </flux:subheading>
            </div>
            <flux:button variant="ghost" icon="arrow-left" :href="route('ia-profiles.index')" wire:navigate>{{ __('Back to IA profiles') }}</flux:button>
        </div>

        <div class="ut-card">
            <div class="overflow-x-auto">
                <table id="deletion-table" class="display" style="width: 100%"
                       data-url="{{ route('ia-profiles.deletion-requests.data') }}">
                    <thead>
                        <tr>
                            <th>{{ __('IA profile') }}</th>
                            <th>{{ __('Office') }}</th>
                            <th>{{ __('Requested by') }}</th>
                            <th>{{ __('Reason') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Requested on') }}</th>
                            <th style="text-align: end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</x-layouts::app>
