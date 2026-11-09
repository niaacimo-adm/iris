<x-layouts::app :title="__('Edit role')">
    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Edit role') }}</flux:heading>
            <flux:subheading>{{ $role->name }} &middot; {{ $role->code }}</flux:subheading>
        </div>

        <form method="POST" action="{{ route('roles.update', $role) }}" class="flex flex-col gap-6">
            @csrf
            @method('PUT')

            @include('roles._form')

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                <flux:button variant="ghost" :href="route('roles.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
