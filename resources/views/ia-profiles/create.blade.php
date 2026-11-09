<x-layouts::app :title="__('New IA profile')">
    <div id="ia-page" data-error="{{ session('error') }}" data-errors="{{ json_encode($errors->all()) }}" class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('New IA profile') }}</flux:heading>
            <flux:subheading>{{ __('Fields marked * are required. Everything else can be filled in later.') }}</flux:subheading>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-300">
                {{ __('Please fix the highlighted fields and try again.') }}
            </div>
        @endif

        <form method="POST" action="{{ route('ia-profiles.store') }}" class="flex flex-col gap-8">
            @csrf

            @include('ia-profiles._form')

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Create IA profile') }}</flux:button>
                <flux:button variant="ghost" :href="route('ia-profiles.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
