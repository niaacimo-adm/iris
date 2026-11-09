@php
    $selectClass = 'w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs dark:border-white/10 dark:bg-zinc-900 dark:text-white';
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="region_id" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-white">{{ __('Region') }}</label>
        <select id="region_id" name="region_id" class="{{ $selectClass }}" required>
            <option value="">{{ __('Select region') }}</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}"
                    @selected((int) old('region_id', $province->region_id) === $region->id || $regions->count() === 1)>
                    {{ $region->name }}
                </option>
            @endforeach
        </select>
        @error('region_id')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
        @enderror
    </div>

    <flux:input
        name="code"
        :label="__('Code')"
        :value="old('code', $province->code)"
        :description="__('Up to 10 characters: letters, numbers and hyphens, e.g. ALB.')"
        required
    />
    <flux:input name="name" :label="__('Name')" :value="old('name', $province->name)" required />
</div>
