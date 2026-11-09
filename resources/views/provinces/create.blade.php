<x-layouts::app :title="__('New province')">
    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('New province') }}</flux:heading>
        </div>

        <form method="POST" action="{{ route('provinces.store') }}" class="flex flex-col gap-6">
            @csrf

            @include('provinces._form')

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Create province') }}</flux:button>
                <flux:button variant="ghost" :href="route('provinces.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
