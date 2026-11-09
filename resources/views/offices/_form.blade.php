@php
    $editing = $office->exists;
    $selectClass = 'w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs disabled:opacity-60 dark:border-white/10 dark:bg-zinc-900 dark:text-white';

    $type = old('office_type', $office->office_type ?? 'imo');

    $selectedProvinces = collect(old('provinces', $editing ? $office->provinces->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <flux:input name="name" :label="__('Office name')" :value="old('name', $office->name)" required />
    <flux:input
        name="code"
        :label="__('Code')"
        :value="old('code', $office->code)"
        :placeholder="__('Up to 20 characters: letters, numbers and hyphens, e.g. ACIMO.')"
        required
    />

    {{-- Region --}}
    <div>
        <label for="region_id" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-white">{{ __('Region') }}</label>
        <select id="region_id" name="region_id" class="{{ $selectClass }}" required>
            <option value="">{{ __('Select region') }}</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}"
                    @selected((int) old('region_id', $office->region_id) === $region->id || $regions->count() === 1)>
                    {{ $region->name }}
                </option>
            @endforeach
        </select>
        @error('region_id')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
        @enderror
    </div>

    {{-- Type --}}
    <div>
        <label for="office_type" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-white">{{ __('Type') }}</label>
        <select id="office_type" name="office_type" class="{{ $selectClass }}" required>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('office_type')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
        @enderror
    </div>

    {{-- Parent --}}
    <div class="md:col-span-2">
        <label for="parent_office_id" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-white">{{ __('Reports to (regional office)') }}</label>
        <select id="parent_office_id" name="parent_office_id" class="{{ $selectClass }}">
            <option value="">{{ __('None') }}</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}" @selected((int) old('parent_office_id', $office->parent_office_id) === $parent->id)>
                    {{ $parent->code }} &mdash; {{ $parent->name }}
                </option>
            @endforeach
        </select>
        @error('parent_office_id')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
        @enderror
        <p class="mt-1 text-xs text-zinc-500">{{ __('Only IMOs report to a regional office. Regional offices have no parent.') }}</p>
    </div>
</div>

{{-- Provinces --}}
<div class="flex flex-col gap-3">
    <div>
        <flux:heading size="lg">{{ __('Provinces covered') }}</flux:heading>
        <flux:subheading>{{ __('The provinces this office is responsible for. Usually filled in for IMOs.') }}</flux:subheading>
    </div>

    @error('provinces')
        <p class="text-sm text-red-500">{{ $message }}</p>
    @enderror
    @error('provinces.*')
        <p class="text-sm text-red-500">{{ $message }}</p>
    @enderror

    <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 sm:grid-cols-2 lg:grid-cols-3 dark:border-zinc-700">
        @forelse ($provinces as $province)
            <label class="flex items-center gap-2 text-sm text-zinc-800 dark:text-zinc-200">
                <input type="checkbox" name="provinces[]" value="{{ $province->id }}" class="rounded border-zinc-300"
                    @checked(in_array($province->id, $selectedProvinces, true))>
                <span><span class="font-mono text-xs">{{ $province->code }}</span> &middot; {{ $province->name }}</span>
            </label>
        @empty
            <p class="text-sm text-zinc-500 sm:col-span-2 lg:col-span-3">{{ __('No provinces exist yet. Create some under Provinces first.') }}</p>
        @endforelse
    </div>
</div>

{{-- Active --}}
<label class="flex items-center gap-2 text-sm text-zinc-800 dark:text-zinc-200">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" class="rounded border-zinc-300"
        @checked(old('is_active', $editing ? $office->is_active : true))>
    {{ __('Office is active (can be assigned to users)') }}
</label>
