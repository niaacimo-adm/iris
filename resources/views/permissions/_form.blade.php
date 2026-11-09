@php
    $editing = $permission->exists;
    $locked  = $editing && $permission->isProtected();
@endphp

<div class="grid gap-6">
    <flux:input
        name="code"
        :label="__('Code')"
        :value="old('code', $permission->code)"
        :readonly="$locked"
        :description="$locked
            ? __('This is a built-in permission used by the application, so its code cannot be changed.')
            : __('Lowercase, in the form module.action, e.g. report.export. The part before the dot groups permissions on the role form.')"
        required
    />

    <flux:input
        name="description"
        :label="__('Description')"
        :value="old('description', $permission->description)"
        :description="__('Shown next to the checkbox on the role form.')"
    />
</div>
