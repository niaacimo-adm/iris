<div class="grid gap-6 md:grid-cols-2">
    <flux:input
        name="code"
        :label="__('Code')"
        :value="old('code', $region->code)"
        :description="__('Up to 10 characters: letters, numbers and hyphens, e.g. V.')"
        required
    />
    <flux:input name="name" :label="__('Name')" :value="old('name', $region->name)" required />
</div>
