@props([
    'sidebar' => false,
])

@if($sidebar)
    <a {{ $attributes->merge(['class' => 'flex w-full flex-col items-center gap-1 px-2 py-2 text-center']) }}>
        {{-- <img src="{{ asset('2020-nia-logo.png') }}" alt="NIA" style="width: 72px; height: 72px;" class="shrink-0 object-contain" /> --}}
        <img src="{{ asset('iris-logo.png') }}" alt="IRIS - Irrigators' Registry and Information System" class="h-auto w-auto max-w-full object-contain" />
    </a>
@else
    <flux:brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <img src="{{ asset('2020-nia-logo.png') }}" alt="NIA" class="size-8 object-contain" />
        </x-slot>
    </flux:brand>
@endif