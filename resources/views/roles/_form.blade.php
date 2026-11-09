@php
    $editing  = $role->exists;
    $locked   = $editing && $role->isProtected();
    $selectClass = 'w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs disabled:opacity-60 dark:border-white/10 dark:bg-zinc-900 dark:text-white';

    $selected = collect(old('permissions', $editing ? $role->permissions->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->all();

    $scope = old('data_scope', $role->data_scope ?? 'office');
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <flux:input name="name" :label="__('Role name')" :value="old('name', $role->name)" required />
    <flux:input
        name="code"
        :label="__('Code')"
        :value="old('code', $role->code)"
        :readonly="$locked"
        :description="$locked ? __('The code of the super admin role cannot be changed.') : null"
        :placeholder="__('Lowercase letters, numbers and underscores, e.g. imo_admin.')"
        required
    />

    <div class="md:col-span-2">
        <flux:input name="description" :label="__('Description')" :value="old('description', $role->description)" />
    </div>

    {{-- Data scope --}}
    <div>
        <label for="data_scope" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-white">{{ __('Data scope') }}</label>
        <select id="data_scope" @if (! $locked) name="data_scope" @endif class="{{ $selectClass }}" @disabled($locked) required>
            @foreach ($dataScopes as $value => $label)
                <option value="{{ $value }}" @selected($scope === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($locked)
            <input type="hidden" name="data_scope" value="{{ $role->data_scope }}">
        @endif
        @error('data_scope')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
        @enderror
        <p class="mt-1 text-xs text-zinc-500">{{ __('Office-scoped roles can only see and manage records of their own office.') }}</p>
    </div>

    {{-- OTP --}}
    <div class="flex items-end pb-6">
        <label class="flex items-center gap-2 text-sm text-zinc-800 dark:text-zinc-200">
            <input type="hidden" name="require_otp_every_login" value="0">
            <input type="checkbox" name="require_otp_every_login" value="1" class="rounded border-zinc-300"
                @checked(old('require_otp_every_login', $role->require_otp_every_login ?? false))>
            {{ __('Require OTP on every login') }}
        </label>
    </div>
</div>

{{-- Permissions --}}
<div class="flex flex-col gap-3">
    <div>
        <flux:heading size="lg">{{ __('Permissions') }}</flux:heading>
        <flux:subheading>
            @if ($locked)
                {{ __('The super admin role always has every permission.') }}
            @else
                {{ __('Tick everything this role is allowed to do.') }}
            @endif
        </flux:subheading>
    </div>

    @error('permissions')
        <p class="text-sm text-red-500">{{ $message }}</p>
    @enderror
    @error('permissions.*')
        <p class="text-sm text-red-500">{{ $message }}</p>
    @enderror

    @forelse ($permissionGroups as $module => $permissions)
        <fieldset data-perm-group class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <legend class="px-2">
                <label class="flex items-center gap-2 text-sm font-semibold text-zinc-800 dark:text-white">
                    <input type="checkbox" data-group-toggle class="rounded border-zinc-300" @disabled($locked)>
                    {{ \Illuminate\Support\Str::headline($module) }}
                </label>
            </legend>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($permissions as $permission)
                    <label class="flex items-start gap-2 text-sm text-zinc-800 dark:text-zinc-200">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                            class="mt-0.5 rounded border-zinc-300"
                            @checked($locked || in_array($permission->id, $selected, true))
                            @disabled($locked)>
                        <span>
                            <span class="font-mono text-xs">{{ $permission->code }}</span>
                            @if ($permission->description)
                                <span class="block text-xs text-zinc-500">{{ $permission->description }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @empty
        <p class="text-sm text-zinc-500">{{ __('No permissions exist yet. Create some under Permissions first.') }}</p>
    @endforelse
</div>
