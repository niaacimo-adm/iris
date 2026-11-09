@php
    $selectClass = 'w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs dark:border-white/10 dark:bg-zinc-900 dark:text-white';
    $labelClass = 'mb-1 block text-sm font-medium text-zinc-800 dark:text-white';
    // 12.5000 -> 12.5, empty -> ''
    $dec = fn ($v) => $v === null || $v === '' ? '' : (rtrim(rtrim((string) $v, '0'), '.') ?: '0');
    $day = fn ($d) => $d?->format('Y-m-d');
@endphp

<div id="ia-form" data-geo="{{ json_encode($geo) }}" class="flex flex-col gap-8">

    {{-- ===== Basic information ===== --}}
    <section class="ia-card">
        <h3 class="ia-section-title">{{ __('Basic information') }}</h3>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <flux:input name="ia_name" :label="__('IA name *')" :value="old('ia_name', $profile->ia_name)" required />
            </div>

            <flux:input name="ia_code" :label="__('IA code')" :value="old('ia_code', $profile->ia_code)" />

            <div>
                <label for="status" class="{{ $labelClass }}">{{ __('Status *') }}</label>
                <select id="status" name="status" class="{{ $selectClass }}" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $profile->status ?: 'operational') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>

            <flux:input name="cis_name" :label="__('Name of CIS (communal irrigation system)')" :value="old('cis_name', $profile->cis_name)" />
            <flux:input name="nis" :label="__('Name of NIS (national irrigation system)')" :value="old('nis', $profile->nis)" />
            <flux:input name="spip_name" :label="__('Name of SPIP (solar power irrigation pump)')" :value="old('spip_name', $profile->spip_name)" />
            <p class="text-xs text-zinc-500 md:col-span-2">{{ __('Fill in the name of each system this IA has. An IA can have one, two or all three.') }}</p>
        </div>
    </section>

    {{-- ===== Location ===== --}}
    <section class="ia-card">
        <h3 class="ia-section-title">{{ __('Location') }}</h3>
        <div class="grid gap-6 md:grid-cols-2">
            {{-- Office: region-wide users choose it; IMO users always use their own. --}}
            @if ($regionWide)
                <div>
                    <label for="office_id" class="{{ $labelClass }}">{{ __('Office (IMO) *') }}</label>
                    <select id="office_id" name="office_id" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select office') }}</option>
                        @foreach ($offices as $office)
                            <option value="{{ $office->id }}" @selected((int) old('office_id', $profile->office_id) === (int) $office->id)>
                                {{ $office->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('office_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
            @else
                <div>
                    <input type="hidden" id="office_id" name="office_id" value="{{ $offices->first()?->id }}">
                    <flux:input :label="__('Office (IMO)')" :value="$offices->first()?->name" disabled />
                </div>
            @endif
            <div class="hidden md:block"></div>

            <div>
                <label for="province_id" class="{{ $labelClass }}">{{ __('Province *') }}</label>
                <select id="province_id" name="province_id" class="{{ $selectClass }}" required
                        data-selected="{{ old('province_id', $profile->province_id) }}"></select>
                @error('province_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="district_id" class="{{ $labelClass }}">{{ __('Congressional district *') }}</label>
                <select id="district_id" name="district_id" class="{{ $selectClass }}" required
                        data-selected="{{ old('district_id', $profile->district_id) }}"></select>
                @error('district_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="md:col-span-2">
                <label for="mailing_address" class="{{ $labelClass }}">{{ __('Mailing address') }}</label>
                <textarea id="mailing_address" name="mailing_address" rows="2" class="{{ $selectClass }}">{{ old('mailing_address', $profile->mailing_address) }}</textarea>
                @error('mailing_address')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    {{-- ===== President & contact ===== --}}
    <section class="ia-card">
        <h3 class="ia-section-title">{{ __('President & contact') }}</h3>
        <div class="grid gap-6 md:grid-cols-2">
            <flux:input name="president_name" :label="__('President name')" :value="old('president_name', $profile->president_name)" />
            <flux:input name="contact_number" :label="__('Contact number')" :value="old('contact_number', $profile->contact_number)" inputmode="tel" />

            <div>
                <label for="assigned_user_id" class="{{ $labelClass }}">{{ __('Assigned to (IMO staff)') }}</label>
                <select id="assigned_user_id" name="assigned_user_id" class="{{ $selectClass }}"
                        data-selected="{{ old('assigned_user_id', $profile->assigned_user_id) }}"></select>
                @error('assigned_user_id')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    {{-- ===== Legal & registration ===== --}}
    <section class="ia-card">
        <h3 class="ia-section-title">{{ __('Registration & contract') }}</h3>
        <div class="grid gap-6 md:grid-cols-2">
            <flux:input type="date" name="date_organized" :label="__('Date organized')" :value="old('date_organized', $day($profile->date_organized))" />
            <flux:input type="date" name="sec_registration_date" :label="__('SEC registration date')" :value="old('sec_registration_date', $day($profile->sec_registration_date))" />
            <flux:input name="sec_registration_number" :label="__('SEC registration number')" :value="old('sec_registration_number', $profile->sec_registration_number)" />
            <flux:input name="ia_tin" :label="__('IA TIN')" :value="old('ia_tin', $profile->ia_tin)" placeholder="123-456-789-000" />
            <flux:input name="existing_contract" :label="__('Existing contract')" :value="old('existing_contract', $profile->existing_contract)" />
            <flux:input type="date" name="contract_effectivity_date" :label="__('Contract effectivity date')" :value="old('contract_effectivity_date', $day($profile->contract_effectivity_date))" />
        </div>
    </section>

    {{-- ===== Area & membership ===== --}}
    <section class="ia-card">
        <h3 class="ia-section-title">{{ __('Area & membership') }}</h3>
        <div class="grid gap-6 md:grid-cols-3">
            <flux:input type="number" step="any" min="0" name="service_area_ha" :label="__('Service area (ha)')" :value="old('service_area_ha', $dec($profile->service_area_ha))" />
            <flux:input type="number" step="any" min="0" name="fusa_ha" :label="__('FUSA (ha)')" :value="old('fusa_ha', $dec($profile->fusa_ha))" />
            <flux:input type="number" step="any" min="0" name="canal_length_km" :label="__('Canal length (km)')" :value="old('canal_length_km', $dec($profile->canal_length_km))" />

            <flux:input type="number" min="0" name="farmer_beneficiaries" :label="__('Farmer beneficiaries')" :value="old('farmer_beneficiaries', $profile->farmer_beneficiaries)" />
            <flux:input type="number" min="0" name="male_members" :label="__('Male members')" :value="old('male_members', $profile->male_members)" />
            <flux:input type="number" min="0" name="female_members" :label="__('Female members')" :value="old('female_members', $profile->female_members)" />

            <flux:input type="number" min="0" name="actual_ia_members" :label="__('Actual IA members')" :value="old('actual_ia_members', $profile->actual_ia_members)" />
            <flux:input type="number" min="0" name="tsags_count" :label="__('No. of TSAGs')" :value="old('tsags_count', $profile->tsags_count)" />
        </div>
        <p id="members-hint" class="mt-3 text-xs text-zinc-500">
            {{ __('Actual members = male + female. It is filled in for you when you type the two numbers.') }}
        </p>
    </section>
</div>
