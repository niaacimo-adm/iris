<x-layouts::app :title="__('New role')">
    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('New role') }}</flux:heading>
            <flux:subheading>{{ __('Name the role, set its data scope and pick its permissions.') }}</flux:subheading>
        </div>

        <form method="POST" action="{{ route('roles.store') }}" class="flex flex-col gap-6">
            @csrf

            @include('roles._form')

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Create role') }}</flux:button>
                <flux:button variant="ghost" :href="route('roles.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
