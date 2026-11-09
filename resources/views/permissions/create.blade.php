<x-layouts::app :title="__('New permission')">
    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('New permission') }}</flux:heading>
            <flux:subheading>{{ __('It is granted to the super admin role automatically. Assign it to other roles from the Roles page.') }}</flux:subheading>
        </div>

        <form method="POST" action="{{ route('permissions.store') }}" class="flex flex-col gap-6">
            @csrf

            @include('permissions._form')

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Create permission') }}</flux:button>
                <flux:button variant="ghost" :href="route('permissions.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
