<x-layouts::app :title="__('LIPA records').' - '.$profile->ia_name">
    <div id="ia-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="flex w-full flex-1 flex-col gap-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('LIPA records') }}</flux:heading>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <a href="{{ route('ia-profiles.show', $profile) }}" class="ut-name">{{ $profile->ia_name }}</a>
                    @if ($profile->ia_code)<span class="ut-sub">{{ $profile->ia_code }}</span>@endif
                    <span class="ut-sub">{{ $profile->province?->name }} · {{ $profile->office?->code }}</span>
                </div>
                <flux:subheading>{{ __('List of Irrigated and Planted Area, per crop year and season.') }}</flux:subheading>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button variant="ghost" icon="arrow-left" :href="route('ia-profiles.show', $profile)" wire:navigate>{{ __('Back to profile') }}</flux:button>
                @if ($canEdit)
                    <flux:button variant="primary" icon="plus" type="button" id="lipa-add">{{ __('Add LIPA record') }}</flux:button>
                @endif
            </div>
        </div>

        @if ($profile->trashed())
            <div class="ia-banner">{{ __('This IA profile is archived, so its LIPA records are read-only. Restore the profile to change them.') }}</div>
        @endif

        {{-- Totals of the rows currently shown in the table --}}
        <div class="ia-stats">
            <div class="ia-stat"><div class="ia-stat-label">{{ __('Records shown') }}</div><div class="ia-stat-value" id="stat-count">—</div></div>
            <div class="ia-stat"><div class="ia-stat-label">{{ __('Service area (ha)') }}</div><div class="ia-stat-value" id="stat-service">—</div></div>
            <div class="ia-stat"><div class="ia-stat-label">{{ __('Irrigated planted area (ha)') }}</div><div class="ia-stat-value" id="stat-planted">—</div></div>
        </div>

        <div class="ut-card">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <select id="lipa-filter-year" class="ia-filter">
                    <option value="">{{ __('All crop years') }}</option>
                    @foreach ($years as $year)
                        <option value="{{ $year }}" @selected($selectedYear === (int) $year)>{{ $year }}</option>
                    @endforeach
                </select>
                <select id="lipa-filter-season" class="ia-filter">
                    <option value="">{{ __('Wet and dry') }}</option>
                    @foreach ($seasons as $value => $label)
                        <option value="{{ $value }}" @selected($selectedSeason === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select id="lipa-filter-sector" class="ia-filter">
                    <option value="">{{ __('All sectors') }}</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector }}">{{ $sector }}</option>
                    @endforeach
                </select>
            </div>

            <div class="overflow-x-auto">
                <table id="lipa-table" class="display" style="width: 100%"
                       data-url="{{ route('ia-profiles.lipa.data', $profile) }}"
                       data-store-url="{{ route('ia-profiles.lipa.store', $profile) }}"
                       data-can-edit="{{ $canEdit ? 1 : 0 }}">
                    <thead>
                        <tr>
                            <th>{{-- sector number, used for sorting --}}</th>
                            <th>{{ __('Period') }}</th>
                            <th>{{ __('Sector') }}</th>
                            <th>{{ __('No.') }}</th>
                            <th>{{ __('Lot no.') }}</th>
                            <th>{{ __('Landowner') }}</th>
                            <th>{{ __('Service area (ha)') }}</th>
                            <th>{{ __('Planted area (ha)') }}</th>
                            <th>{{ __('Variety') }}</th>
                            <th>{{ __('Date sown') }}</th>
                            <th>{{ __('Date planted') }}</th>
                            <th>{{ __('Exp. harvest') }}</th>
                            <th>{{ __('RSBSA no.') }}</th>
                            <th>{{ __('Remarks') }}</th>
                            <th style="text-align: end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        {{-- ===== Add / edit modal ===== --}}
        @if ($canEdit)
            <div id="lipa-modal" class="ia-modal" hidden>
                <form id="lipa-form" class="ia-modal-panel" novalidate autocomplete="off">
                    <div class="ia-modal-head">
                        <h3 id="lipa-modal-title">{{ __('Add LIPA record') }}</h3>
                        <button type="button" class="ia-modal-x" data-modal-close aria-label="{{ __('Close') }}">&times;</button>
                    </div>

                    <div class="ia-modal-body">
                        <div>
                            <div class="ia-fieldset-title">{{ __('Period and location') }}</div>
                            <div class="ia-grid">
                                <div>
                                    <label class="ia-label" for="lipa-crop_year">{{ __('Crop year *') }}</label>
                                    <input class="ia-input" id="lipa-crop_year" name="crop_year" type="number" min="2000" max="{{ now()->year + 1 }}" value="{{ now()->year }}">
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-season">{{ __('Season *') }}</label>
                                    <select class="ia-input" id="lipa-season" name="season">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($seasons as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-sector">{{ __('Sector *') }}</label>
                                    <input class="ia-input" id="lipa-sector" name="sector" list="lipa-sector-list" maxlength="50" placeholder="Sector 1">
                                    <datalist id="lipa-sector-list">
                                        @foreach ($sectors as $sector)<option value="{{ $sector }}"></option>@endforeach
                                    </datalist>
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-no">{{ __('No.') }}</label>
                                    <input class="ia-input" id="lipa-no" name="no" type="number" min="1">
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-lot_no">{{ __('Lot no.') }}</label>
                                    <input class="ia-input" id="lipa-lot_no" name="lot_no" maxlength="30">
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="ia-fieldset-title">{{ __('Landowner') }}</div>
                            <div class="ia-grid">
                                <div>
                                    <label class="ia-label" for="lipa-landowner_first_name">{{ __('First name *') }}</label>
                                    <input class="ia-input" id="lipa-landowner_first_name" name="landowner_first_name" maxlength="100">
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-landowner_mi">{{ __('M.I.') }}</label>
                                    <input class="ia-input" id="lipa-landowner_mi" name="landowner_mi" maxlength="10">
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-landowner_last_name">{{ __('Last name *') }}</label>
                                    <input class="ia-input" id="lipa-landowner_last_name" name="landowner_last_name" maxlength="100">
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-rsbsa_reg_no">{{ __('RSBSA reg. no.') }}</label>
                                    <input class="ia-input" id="lipa-rsbsa_reg_no" name="rsbsa_reg_no" maxlength="50" placeholder="05-05-14-032-000056">
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="ia-fieldset-title">{{ __('Area and variety') }}</div>
                            <div class="ia-grid">
                                <div>
                                    <label class="ia-label" for="lipa-service_area_ha">{{ __('Service area (ha) *') }}</label>
                                    <input class="ia-input" id="lipa-service_area_ha" name="service_area_ha" type="number" step="0.0001" min="0" inputmode="decimal">
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-irrigated_planted_area_ha">{{ __('Irrigated planted area (ha) *') }}</label>
                                    <input class="ia-input" id="lipa-irrigated_planted_area_ha" name="irrigated_planted_area_ha" type="number" step="0.0001" min="0" inputmode="decimal">
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-variety_inbred">{{ __('Inbred variety') }}</label>
                                    <input class="ia-input" id="lipa-variety_inbred" name="variety_inbred" maxlength="30">
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-variety_hybrid">{{ __('Hybrid variety') }}</label>
                                    <input class="ia-input" id="lipa-variety_hybrid" name="variety_hybrid" maxlength="30">
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="ia-fieldset-title">{{ __('Schedule') }}</div>
                            <div class="ia-grid">
                                <div>
                                    <label class="ia-label" for="lipa-date_sown">{{ __('Date sown') }}</label>
                                    <input class="ia-input" id="lipa-date_sown" name="date_sown" type="date">
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-date_planted">{{ __('Date planted') }}</label>
                                    <input class="ia-input" id="lipa-date_planted" name="date_planted" type="date">
                                </div>
                                <div>
                                    <label class="ia-label" for="lipa-expected_harvest_date">{{ __('Expected harvest date') }}</label>
                                    <input class="ia-input" id="lipa-expected_harvest_date" name="expected_harvest_date" type="date">
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="ia-fieldset-title">{{ __('Other') }}</div>
                            <div class="ia-grid">
                                <div class="span-2">
                                    <label class="ia-label" for="lipa-crop_insurance">{{ __('Crop insurance') }}</label>
                                    <input class="ia-input" id="lipa-crop_insurance" name="crop_insurance" maxlength="150">
                                </div>
                                <div class="span-2">
                                    <label class="ia-label" for="lipa-remarks">{{ __('Remarks') }}</label>
                                    <input class="ia-input" id="lipa-remarks" name="remarks" maxlength="255">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ia-modal-foot">
                        <button type="button" class="ut-btn" data-modal-close>{{ __('Cancel') }}</button>
                        <button type="submit" class="ut-btn" id="lipa-save" style="background:#16a34a;color:#fff;border-color:#16a34a">{{ __('Save record') }}</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-layouts::app>
