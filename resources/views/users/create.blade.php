<x-layouts::app :title="__('New user')">
    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('New user') }}</flux:heading>
            <flux:subheading>{{ __('The account is active immediately. Share the temporary password with the user.') }}</flux:subheading>
        </div>

        <form method="POST" action="{{ route('users.store') }}" class="flex flex-col gap-6">
            @csrf

            @include('users._form')

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Create user') }}</flux:button>
                <flux:button variant="ghost" :href="route('users.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
