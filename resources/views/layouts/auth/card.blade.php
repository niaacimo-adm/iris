<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-neutral-100 antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-muted flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col items-center gap-6">

                <a href="{{ route('home') }}" class="flex items-center justify-center gap-0 font-medium" wire:navigate>
                    <img src="{{ asset('2020-nia-logo.png') }}" alt="NIA" style="width: 120px; height: 120px;" class="shrink-0 object-contain" />
                    <img src="{{ asset('iris-logo.png') }}" alt="IRIS - Irrigators' Registry and Information System" style="width: 460;" class="h-auto min-w-0 object-contain dark:hidden" />
                    <span class="sr-only">{{ config('app.name', 'IRIS') }}</span>
                </a>

                {{-- Card (fixed min height so it doesn't change between login and forgot password) --}}
                <div class="w-full">
                    <div class="flex min-h-[26rem] flex-col justify-center rounded-xl border bg-white text-stone-800 shadow-xs dark:border-stone-800 dark:bg-stone-950">
                        <div class="px-10 py-8">{{ $slot }}</div>
                    </div>
                </div>

            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>