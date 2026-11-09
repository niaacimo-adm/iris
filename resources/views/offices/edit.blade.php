<x-layouts::app :title="__('Edit office')">
    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Edit office') }}</flux:heading>
            <flux:subheading>{{ $office->name }} &middot; {{ $office->code }}</flux:subheading>
        </div>

        <form method="POST" action="{{ route('offices.update', $office) }}" class="flex flex-col gap-6">
            @csrf
            @method('PUT')

            @include('offices._form')

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                <flux:button variant="ghost" :href="route('offices.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
