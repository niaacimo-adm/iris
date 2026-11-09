<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Main')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @if (auth()->user()->hasPermission('ia.view'))
                        <flux:sidebar.item icon="building-office-2" :href="route('ia-profiles.index')" :current="request()->routeIs('ia-profiles.*') && ! request()->routeIs('ia-profiles.deletion-requests.*')" wire:navigate>
                            {{ __('IA Profiles') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->hasPermission('ia.view'))
                        @php($deletionBadge = \App\Models\IaDeletionRequest::badgeCount(auth()->user()))
                        <flux:sidebar.item icon="trash" :href="route('ia-profiles.deletion-requests.index')" :current="request()->routeIs('ia-profiles.deletion-requests.*')"
                                           :badge="$deletionBadge > 0 ? $deletionBadge : null" badge-color="red" wire:navigate>
                            {{ __('Deletion requests') }}
                        </flux:sidebar.item>
                    @endif
                    </flux:sidebar.group>
                    @if (auth()->user()->hasPermission('user.view'))
                    <flux:sidebar.group :heading="__('Settings')" class="grid">
                        <flux:sidebar.item icon="user-group" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->hasPermission('office.manage') && auth()->user()->isSuperAdmin())
                        <flux:sidebar.item  icon="map" :href="route('regions.index')"
                            :current="request()->routeIs('regions.*')" wire:navigate>{{ __('Regions') }}</flux:sidebar.item>
                        <flux:sidebar.item  icon="map-pin" :href="route('provinces.index')"
                            :current="request()->routeIs('provinces.*')" wire:navigate>{{ __('Provinces') }}</flux:sidebar.item>
                        <flux:sidebar.item  icon="home" :href="route('offices.index')"
                            :current="request()->routeIs('offices.*')" wire:navigate>{{ __('Offices') }}</flux:sidebar.item>
                    @endif
                     @if (auth()->user()->hasPermission('role.view'))
                        <flux:sidebar.item icon="shield-check" :href="route('roles.index')"
                            :current="request()->routeIs('roles.*')" wire:navigate>
                            {{ __('Roles') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasPermission('permission.view'))
                        <flux:sidebar.item icon="key" :href="route('permissions.index')"
                            :current="request()->routeIs('permissions.*')" wire:navigate>
                            {{ __('Permissions') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
