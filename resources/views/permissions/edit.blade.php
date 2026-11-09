<x-layouts::app :title="__('Edit permission')">
    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Edit permission') }}</flux:heading>
            <flux:subheading>{{ $permission->code }}</flux:subheading>
        </div>

        <form method="POST" action="{{ route('permissions.update', $permission) }}" class="flex flex-col gap-6">
            @csrf
            @method('PUT')

            @include('permissions._form')

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                <flux:button variant="ghost" :href="route('permissions.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
