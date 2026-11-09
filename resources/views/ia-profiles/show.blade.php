@php
    $n = fn ($v, $d = 2) => $v === null ? null : number_format((float) $v, $d);
    $d = fn ($v) => $v?->format('M d, Y');
    $sections = [
        __('Basic information') => [
            __('IA name') => $profile->ia_name,
            __('IA code') => $profile->ia_code,
            __('Name of CIS') => $profile->cis_name,
            __('Name of NIS') => $profile->nis,
            __('Name of SPIP') => $profile->spip_name,
            __('Assigned to') => $profile->assignedUser?->name,
            __('President') => $profile->president_name,
            __('Contact number') => $profile->contact_number,
        ],
        __('Area & membership') => [
            __('Service area (ha)') => $n($profile->service_area_ha),
            __('FUSA (ha)') => $n($profile->fusa_ha),
            __('Canal length (km)') => $n($profile->canal_length_km, 3),
            __('Farmer beneficiaries') => $n($profile->farmer_beneficiaries, 0),
            __('Actual IA members') => $n($profile->actual_ia_members, 0),
            __('Male members') => $n($profile->male_members, 0),
            __('Female members') => $n($profile->female_members, 0),
            __('No. of TSAGs') => $n($profile->tsags_count, 0),
        ],
        __('Location & legal') => [
            __('Office') => $profile->office?->name,
            __('Province') => $profile->province?->name,
            __('District') => $profile->district?->name,
            __('Mailing address') => $profile->mailing_address,
            __('Date organized') => $d($profile->date_organized),
            __('SEC registration date') => $d($profile->sec_registration_date),
            __('SEC registration number') => $profile->sec_registration_number,
            __('IA TIN') => $profile->ia_tin,
            __('Existing contract') => $profile->existing_contract,
            __('Contract effectivity date') => $d($profile->contract_effectivity_date),
        ],
    ];
    $inputClass = 'w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs dark:border-white/10 dark:bg-zinc-900 dark:text-white';
@endphp

<x-layouts::app :title="$profile->ia_name">
    <div id="ia-page"
         data-status="{{ session('status') }}"
         data-error="{{ session('error') }}"
         data-csrf="{{ csrf_token() }}"
         class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">

        @if ($archived)
            <div class="ia-banner">
                {{ __('This IA profile is archived (:date, by :user). It is read-only until it is restored.', [
                    'date' => $profile->deleted_at?->format('M d, Y h:i A'),
                    'user' => $profile->updater?->name ?? __('unknown'),
                ]) }}
            </div>
        @endif

        @if ($pendingDeletion)
            <div class="ia-banner">
                {{ __('Deletion requested by :user on :date. Waiting for approval from Super Admin / Regional Staff.', [
                    'user' => $pendingDeletion->requester?->name ?? __('a user'),
                    'date' => $pendingDeletion->created_at?->format('M d, Y h:i A'),
                ]) }}
                <a href="{{ route('ia-profiles.deletion-requests.index') }}" class="underline">{{ __('View request') }}</a>
            </div>
        @endif

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ $profile->ia_name }}</flux:heading>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <span class="ut-badge {{ $profile->status === 'operational' ? 'ut-badge-active' : 'ut-badge-inactive' }}">
                        {{ $statusLabel ?? \App\Models\IaProfile::STATUSES[$profile->status] ?? $profile->status }}
                    </span>
                    @foreach ($profile->systems() as $system)<span class="ia-sys ia-sys-{{ strtolower($system) }}">{{ $system }}</span>@endforeach
                    @if ($profile->ia_code)<span class="ut-sub">{{ $profile->ia_code }}</span>@endif
                    <span class="ut-sub">{{ $profile->office?->code }}</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button variant="ghost" icon="arrow-left" :href="$archived ? route('ia-profiles.archived') : route('ia-profiles.index')" wire:navigate>{{ $archived ? __('Back to archive') : __('Back') }}</flux:button>
                <flux:button variant="ghost" icon="table-cells" :href="route('ia-profiles.lipa.index', $profile)" wire:navigate>{{ __('LIPA records') }}</flux:button>
                @if ($canRestore)
                    <button type="button" class="ut-btn" data-ia-restore-profile
                            data-url="{{ route('ia-profiles.restore', $profile->id) }}"
                            data-name="{{ $profile->ia_name }}"
                            data-redirect="{{ route('ia-profiles.show', $profile->id) }}">{{ __('Restore') }}</button>
                @endif
                @if ($canEdit)
                    <flux:button variant="primary" icon="pencil-square" :href="route('ia-profiles.edit', $profile)" wire:navigate>{{ __('Edit') }}</flux:button>
                @endif
                @if ($canDelete)
                    @if ($pendingDeletion)
                        <a href="{{ route('ia-profiles.deletion-requests.index') }}" class="ut-btn" title="{{ __('Waiting for approval') }}">{{ __('Deletion pending') }}</a>
                    @else
                        <button type="button" class="ut-btn ut-btn-danger" data-ia-archive
                                data-mode="{{ $deleteMode }}"
                                data-url="{{ route('ia-profiles.destroy', $profile) }}"
                                data-request-url="{{ route('ia-profiles.deletion-requests.store', $profile) }}"
                                data-name="{{ $profile->ia_name }}"
                                data-redirect="{{ route('ia-profiles.index') }}">{{ __('Delete') }}</button>
                    @endif
                @endif
            </div>
        </div>

        @foreach ($sections as $title => $items)
            <section class="ia-card">
                <h3 class="ia-section-title">{{ $title }}</h3>
                <dl class="ia-dl">
                    @foreach ($items as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach

        {{-- ===== LIPA records (summary; the full list and the add / edit / delete are on its own page) ===== --}}
        <section class="ia-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="ia-section-title" style="margin:0">{{ __('LIPA records') }}</h3>
                <flux:button size="sm" icon="table-cells" :href="route('ia-profiles.lipa.index', $profile)" wire:navigate>
                    {{ $canEdit ? __('Open / manage LIPA') : __('Open LIPA records') }}
                </flux:button>
            </div>

            @forelse ($lipaSummary as $line)
                <a href="{{ route('ia-profiles.lipa.index', [$profile, 'year' => $line->crop_year, 'season' => $line->season]) }}"
                   class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 py-2.5 text-sm last:border-0 hover:bg-zinc-50 dark:border-white/10 dark:hover:bg-white/5">
                    <span class="font-medium text-zinc-900 dark:text-white">CY {{ $line->crop_year }} · {{ \App\Models\IaLipaEntry::SEASONS[$line->season] ?? $line->season }}</span>
                    <span class="ut-sub">
                        {{ number_format($line->lots) }} {{ __('records') }} ·
                        {{ __('service') }} {{ number_format((float) $line->service_area, 2) }} ha ·
                        {{ __('planted') }} {{ number_format((float) $line->planted_area, 2) }} ha
                    </span>
                </a>
            @empty
                <p class="text-sm text-zinc-500">{{ __('No LIPA records yet.') }}</p>
            @endforelse
        </section>

        {{-- ===== Officers ===== --}}
        <section class="ia-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="ia-section-title" style="margin:0">{{ __('IA officers') }}</h3>
                @if ($canEdit)
                    <flux:button size="sm" variant="primary" icon="plus" type="button" data-officer-add>{{ __('Add officer') }}</flux:button>
                @endif
            </div>

            @forelse ($profile->officers as $officer)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 py-3 last:border-0 dark:border-white/10">
                    <div>
                        <div class="ut-name">{{ $officer->officer_name }}</div>
                        <div class="ut-sub">
                            {{ collect([$officer->position, $officer->contact_number, $officer->email])->filter()->implode(' · ') ?: '—' }}
                        </div>
                    </div>
                    @if ($canEdit)
                        <div class="ut-actions">
                            <button type="button" class="ut-btn" data-officer-edit
                                    data-officer="{{ json_encode(['id' => $officer->id, 'officer_name' => $officer->officer_name, 'position' => $officer->position, 'contact_number' => $officer->contact_number, 'email' => $officer->email, 'url' => route('ia-profiles.officers.update', [$profile, $officer])]) }}">{{ __('Edit') }}</button>
                            <button type="button" class="ut-btn ut-btn-danger" data-officer-delete
                                    data-url="{{ route('ia-profiles.officers.destroy', [$profile, $officer]) }}"
                                    data-name="{{ $officer->officer_name }}">{{ __('Delete') }}</button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-zinc-500">{{ __('No officers recorded yet.') }}</p>
            @endforelse
        </section>

        @if ($canEdit)
            <div id="officer-modal" class="ia-modal" hidden>
                <form id="officer-form" class="ia-modal-panel is-narrow" novalidate autocomplete="off"
                      data-store-url="{{ route('ia-profiles.officers.store', $profile) }}">
                    <div class="ia-modal-head">
                        <h3 id="officer-modal-title">{{ __('Add officer') }}</h3>
                        <button type="button" class="ia-modal-x" data-modal-close aria-label="{{ __('Close') }}">&times;</button>
                    </div>
                    <div class="ia-modal-body">
                        <div>
                            <label class="ia-label" for="officer-officer_name">{{ __('Name *') }}</label>
                            <input class="ia-input" id="officer-officer_name" name="officer_name" maxlength="255">
                        </div>
                        <div>
                            <label class="ia-label" for="officer-position">{{ __('Position') }}</label>
                            <input class="ia-input" id="officer-position" name="position" maxlength="100" placeholder="{{ __('e.g. Treasurer') }}">
                        </div>
                        <div>
                            <label class="ia-label" for="officer-contact_number">{{ __('Contact number') }}</label>
                            <input class="ia-input" id="officer-contact_number" name="contact_number" inputmode="tel" maxlength="20">
                        </div>
                        <div>
                            <label class="ia-label" for="officer-email">{{ __('Email') }}</label>
                            <input class="ia-input" id="officer-email" name="email" type="email" maxlength="190">
                        </div>
                    </div>
                    <div class="ia-modal-foot">
                        <button type="button" class="ut-btn" data-modal-close>{{ __('Cancel') }}</button>
                        <button type="submit" class="ut-btn" id="officer-save" style="background:#16a34a;color:#fff;border-color:#16a34a">{{ __('Save officer') }}</button>
                    </div>
                </form>
            </div>
        @endif

        {{-- ===== History ===== --}}
        <section class="ia-card">
            <h3 class="ia-section-title">{{ __('Activity history') }}</h3>

            @forelse ($history as $item)
                <div class="ia-history-item ia-history-{{ $item->action }}">
                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $item->description }}</div>
                    @if ($item->field_name)
                        <div class="ia-history-change">
                            <span class="ia-old">{{ $item->old_value ?? '—' }}</span>
                            <span>→</span>
                            <span class="ia-new">{{ $item->new_value ?? '—' }}</span>
                        </div>
                    @endif
                    <div class="ut-sub">
                        {{ $item->user?->name ?? __('System') }} · {{ $item->created_at?->format('M d, Y h:i A') }}
                    </div>
                </div>
            @empty
                <p class="text-sm text-zinc-500">{{ __('No activity recorded yet.') }}</p>
            @endforelse
        </section>
    </div>
</x-layouts::app>
