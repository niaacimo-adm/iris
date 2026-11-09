@php
    $editing = $user->exists;
    $selectClass = 'w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs dark:border-white/10 dark:bg-zinc-900 dark:text-white';
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <flux:input name="first_name" :label="__('First name')" :value="old('first_name', $user->first_name)" required />
    <flux:input name="middle_name" :label="__('Middle name')" :value="old('middle_name', $user->middle_name)" />
    <flux:input name="last_name" :label="__('Last name')" :value="old('last_name', $user->last_name)" required />
    <flux:input name="position_title" :label="__('Position')" :value="old('position_title', $user->position_title)" />

    <flux:input name="email" type="email" :label="__('Email')" :value="old('email', $user->email)" required />
    <flux:input name="username" :label="__('Username')" :value="old('username', $user->username)" />
    <flux:input name="phone_number" :label="__('Phone number')" :value="old('phone_number', $user->phone_number)" />
    <div></div>

    {{-- Office --}}
    <div>
        <label for="office_id" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-white">{{ __('Office') }}</label>
        <select id="office_id" name="office_id" class="{{ $selectClass }}" required>
            <option value="">{{ __('Select office') }}</option>
            @foreach ($offices as $office)
                <option value="{{ $office->id }}"
                    @selected((int) old('office_id', $user->office_id) === $office->id || $offices->count() === 1)>
                    {{ $office->name }}
                </option>
            @endforeach
        </select>
        @error('office_id')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
        @enderror
    </div>

    {{-- Role --}}
    <div>
        <label for="role_id" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-white">{{ __('Role') }}</label>
        <select id="role_id" name="role_id" class="{{ $selectClass }}" required>
            <option value="">{{ __('Select role') }}</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((int) old('role_id', $user->role_id) === $role->id)>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
        @error('role_id')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
        @enderror
        @if ($editing && $user->is(auth()->user()))
            <p class="mt-1 text-xs text-zinc-500">{{ __('You cannot change your own role or deactivate yourself.') }}</p>
        @endif
    </div>

    {{-- Password --}}
    <flux:input
        name="password"
        type="password"
        :label="$editing ? __('New password (leave blank to keep current)') : __('Temporary password')"
        autocomplete="new-password"
        :required="! $editing"
    />
    <flux:input
        name="password_confirmation"
        type="password"
        :label="__('Confirm password')"
        autocomplete="new-password"
        :required="! $editing"
    />
</div>

<div class="flex flex-col gap-3">
    <label class="flex items-center gap-2 text-sm text-zinc-800 dark:text-zinc-200">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-zinc-300"
            @checked(old('is_active', $editing ? $user->is_active : true))>
        {{ __('Account is active') }}
    </label>

    <label class="flex items-center gap-2 text-sm text-zinc-800 dark:text-zinc-200">
        <input type="hidden" name="must_change_password" value="0">
        <input type="checkbox" name="must_change_password" value="1" class="rounded border-zinc-300"
            @checked(old('must_change_password', $editing ? $user->must_change_password : true))>
        {{ __('Require password change at next login') }}
    </label>
</div>
