@php
    $n = fn ($v, $d = 0) => number_format((float) $v, $d);
@endphp

<x-layouts::app :title="__('Dashboard')">
    {{-- Dashboard styles live here so the page looks right even before the CSS bundle is rebuilt --}}
    <style>
/* Dashboard bars and mini table */
a.ia-stat { display: block; text-decoration: none; }
a.ia-stat:hover { border-color: #16a34a; }
.ia-bar-row { display: grid; grid-template-columns: minmax(7rem, 11rem) 1fr 3rem; align-items: center; gap: 0.6rem; padding: 0.3rem 0; font-size: 0.85rem; }
.ia-bar-label { color: rgb(63 63 70); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dark .ia-bar-label { color: rgb(228 228 231); }
.ia-bar-track { height: 0.6rem; border-radius: 9999px; background: rgb(244 244 245); overflow: hidden; }
.dark .ia-bar-track { background: rgb(255 255 255 / 0.08); }
.ia-bar-fill { height: 100%; border-radius: 9999px; background: #16a34a; min-width: 2px; }
.ia-bar-fill.ia-bar-nis { background: #3b82f6; }
.ia-bar-fill.ia-bar-spip { background: #f59e0b; }
.ia-bar-fill.ia-bar-office { background: #0f766e; }
.ia-bar-value { text-align: right; font-variant-numeric: tabular-nums; color: rgb(24 24 27); font-weight: 500; }
.dark .ia-bar-value { color: #fff; }
.ia-mini-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.ia-mini-table th { padding: 0.5rem 0.75rem; text-align: left; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: rgb(113 113 122); border-bottom: 1px solid rgb(228 228 231); }
.ia-mini-table td { padding: 0.55rem 0.75rem; border-bottom: 1px solid rgb(244 244 245); color: rgb(24 24 27); }
.dark .ia-mini-table th, .dark .ia-mini-table td { border-color: rgb(255 255 255 / 0.08); color: rgb(228 228 231); }
.ia-mini-table .text-right { text-align: right; }
    </style>

    <div id="ia-page" class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
                <flux:subheading>
                    {{ __('Welcome, :name.', ['name' => auth()->user()->name]) }}
                    @if ($canSeeIa) {{ __('Showing: :scope', ['scope' => $scopeLabel]) }} @endif
                </flux:subheading>
            </div>
            @if ($canSeeIa)
                <flux:button variant="primary" icon="building-office-2" :href="route('ia-profiles.index')" wire:navigate>{{ __('IA Profiles') }}</flux:button>
            @endif
        </div>

        @unless ($canSeeIa)
            <div class="ia-card text-sm text-zinc-500">{{ __('Your role has no dashboard widgets yet.') }}</div>
        @else
            {{-- Headline numbers --}}
            <div class="ia-stats">
                <a href="{{ route('ia-profiles.index') }}" class="ia-stat"><div class="ia-stat-label">{{ __('IA profiles') }}</div><div class="ia-stat-value">{{ $n($totals->total) }}</div></a>
                <div class="ia-stat"><div class="ia-stat-label">{{ __('Operational') }}</div><div class="ia-stat-value">{{ $n($totals->operational) }}</div></div>
                <div class="ia-stat"><div class="ia-stat-label">{{ __('Non-operational') }}</div><div class="ia-stat-value">{{ $n($totals->non_operational) }}</div></div>
                <div class="ia-stat"><div class="ia-stat-label">{{ __('Service area (ha)') }}</div><div class="ia-stat-value">{{ $n($totals->service_area, 2) }}</div></div>
                <div class="ia-stat"><div class="ia-stat-label">{{ __('IA members') }}</div><div class="ia-stat-value">{{ $n($totals->members) }}</div></div>
                <a href="{{ route('ia-profiles.archived') }}" class="ia-stat"><div class="ia-stat-label">{{ __('Archived') }}</div><div class="ia-stat-value">{{ $n($archivedCount) }}</div></a>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Systems --}}
                <section class="ia-card">
                    <h3 class="ia-section-title">{{ __('Irrigation systems') }}</h3>
                    @php
                        $systems = [
                            ['CIS', __('Communal (CIS)'), $totals->cis, 'ia-sys-cis'],
                            ['NIS', __('National (NIS)'), $totals->nis, 'ia-sys-nis'],
                            ['SPIP', __('Solar pump (SPIP)'), $totals->spip, 'ia-sys-spip'],
                        ];
                        $max = max(1, (int) $totals->total);
                    @endphp
                    @foreach ($systems as [$code, $label, $count, $cls])
                        <div class="ia-bar-row">
                            <div class="ia-bar-label"><span class="ia-sys {{ $cls }}">{{ $code }}</span> {{ $label }}</div>
                            <div class="ia-bar-track"><div class="ia-bar-fill ia-bar-{{ strtolower($code) }}" style="width: {{ round(($count / $max) * 100) }}%"></div></div>
                            <div class="ia-bar-value">{{ $n($count) }}</div>
                        </div>
                    @endforeach
                    <p class="ut-sub mt-3">{{ __('An IA can have more than one system, so the counts can add up to more than the total.') }}</p>
                </section>

                {{-- Per province / office --}}
                <section class="ia-card">
                    <h3 class="ia-section-title">{{ $regionWide ? __('IAs per office and province') : __('IAs per province') }}</h3>
                    @php $topMax = max(1, (int) ($byProvince->max('total') ?? 1), (int) ($byOffice->max('total') ?? 1)); @endphp
                    @foreach ($byOffice as $row)
                        <div class="ia-bar-row">
                            <div class="ia-bar-label"><strong>{{ $row->label }}</strong></div>
                            <div class="ia-bar-track"><div class="ia-bar-fill ia-bar-office" style="width: {{ round(($row->total / $topMax) * 100) }}%"></div></div>
                            <div class="ia-bar-value">{{ $n($row->total) }}</div>
                        </div>
                    @endforeach
                    @if ($byOffice->isNotEmpty())<div class="my-2 border-t border-zinc-200 dark:border-white/10"></div>@endif
                    @forelse ($byProvince as $row)
                        <div class="ia-bar-row">
                            <div class="ia-bar-label">{{ $row->label }}</div>
                            <div class="ia-bar-track"><div class="ia-bar-fill" style="width: {{ round(($row->total / $topMax) * 100) }}%"></div></div>
                            <div class="ia-bar-value">{{ $n($row->total) }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">{{ __('No IA profiles yet.') }}</p>
                    @endforelse
                </section>
            </div>

            {{-- LIPA --}}
            <section class="ia-card">
                <h3 class="ia-section-title">{{ __('LIPA by crop year and season') }}</h3>
                @if ($lipaPeriods->isEmpty())
                    <p class="text-sm text-zinc-500">{{ __('No LIPA records yet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="ia-mini-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Period') }}</th>
                                    <th class="text-right">{{ __('IAs') }}</th>
                                    <th class="text-right">{{ __('Records') }}</th>
                                    <th class="text-right">{{ __('Service area (ha)') }}</th>
                                    <th class="text-right">{{ __('Planted area (ha)') }}</th>
                                    <th style="min-width: 9rem">{{ __('Planted vs service') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lipaPeriods as $p)
                                    @php $pct = (float) $p->service_area > 0 ? min(100, round(((float) $p->planted_area / (float) $p->service_area) * 100)) : 0; @endphp
                                    <tr>
                                        <td class="font-medium">CY {{ $p->crop_year }} · {{ \App\Models\IaLipaEntry::SEASONS[$p->season] ?? $p->season }}</td>
                                        <td class="text-right">{{ $n($p->ias) }}</td>
                                        <td class="text-right">{{ $n($p->lots) }}</td>
                                        <td class="text-right">{{ $n($p->service_area, 2) }}</td>
                                        <td class="text-right">{{ $n($p->planted_area, 2) }}</td>
                                        <td>
                                            <div class="ia-bar-row" style="grid-template-columns: 1fr 2.5rem">
                                                <div class="ia-bar-track"><div class="ia-bar-fill" style="width: {{ $pct }}%"></div></div>
                                                <div class="ia-bar-value">{{ $pct }}%</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Needs attention + delete requests --}}
                <section class="ia-card">
                    <h3 class="ia-section-title">{{ __('Needs attention') }}</h3>
                    @foreach ($attention as $item)
                        <div class="flex items-center justify-between border-b border-zinc-200 py-2.5 text-sm last:border-0 dark:border-white/10">
                            <span class="text-zinc-700 dark:text-zinc-200">{{ $item['label'] }}</span>
                            <span class="ia-sys {{ $item['value'] > 0 ? 'ia-sys-spip' : 'ia-sys-cis' }}">{{ $n($item['value']) }}</span>
                        </div>
                    @endforeach

                    <div class="mt-5 flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $isApprover ? __('Deletion requests to decide') : __('Your pending deletion requests') }}</h4>
                        <a href="{{ route('ia-profiles.deletion-requests.index') }}" class="ut-name text-sm" wire:navigate>{{ __('Open') }} ({{ $n($pendingCount) }})</a>
                    </div>
                    @forelse ($pendingRequests as $req)
                        <div class="mt-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-white/10">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $req->profile?->ia_name }}</div>
                            <div class="ut-sub">{{ $req->requester?->name }} · {{ $req->created_at?->diffForHumans() }}</div>
                        </div>
                    @empty
                        @if ($isApprover)<p class="mt-2 text-sm text-zinc-500">{{ __('Nothing is waiting for your decision.') }}</p>@endif
                    @endforelse
                </section>

                {{-- Recent activity --}}
                <section class="ia-card">
                    <h3 class="ia-section-title">{{ __('Recent activity') }}</h3>
                    @forelse ($activity as $item)
                        <div class="ia-history-item ia-history-{{ $item->action }}">
                            <div class="text-sm font-medium text-zinc-900 dark:text-white">
                                <a href="{{ route('ia-profiles.show', $item->entity_id) }}" wire:navigate>{{ $item->description }}</a>
                            </div>
                            <div class="ut-sub">{{ $item->user?->name ?? __('System') }} · {{ $item->created_at?->diffForHumans() }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">{{ __('No activity recorded yet.') }}</p>
                    @endforelse
                </section>
            </div>
        @endunless
    </div>
</x-layouts::app>
