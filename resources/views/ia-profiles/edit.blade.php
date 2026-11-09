<x-layouts::app :title="__('Edit IA profile')">
    <div id="ia-page" data-error="{{ session('error') }}" data-errors="{{ json_encode($errors->all()) }}" class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Edit IA profile') }}</flux:heading>
            <flux:subheading>{{ $profile->ia_name }}</flux:subheading>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-300">
                {{ __('Please fix the highlighted fields and try again.') }}
            </div>
        @endif

        <form method="POST" action="{{ route('ia-profiles.update', $profile) }}" class="flex flex-col gap-8">
            @csrf
            @method('PUT')

            @include('ia-profiles._form')

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                <flux:button variant="ghost" :href="route('ia-profiles.show', $profile)" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
