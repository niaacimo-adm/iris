<x-layouts::app :title="__('New office')">
    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('New office') }}</flux:heading>
            <flux:subheading>{{ __('Add a regional office or an Irrigation Management Office (IMO).') }}</flux:subheading>
        </div>

        <form method="POST" action="{{ route('offices.store') }}" class="flex flex-col gap-6">
            @csrf

            @include('offices._form')

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Create office') }}</flux:button>
                <flux:button variant="ghost" :href="route('offices.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
