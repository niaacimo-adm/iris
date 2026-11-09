@php
    $n = fn ($v, $d = 0) => number_format((float) $v, $d);

    $total       = (int) $totals->total;
    $operational = (int) $totals->operational;
    $nonOp       = (int) $totals->non_operational;
    $opPct       = $total > 0 ? round(($operational / $total) * 100) : 0;

    $systems = [
        ['CIS',  __('Communal'),   (int) $totals->cis,  'var(--d-cis)'],
        ['NIS',  __('National'),   (int) $totals->nis,  'var(--d-nis)'],
        ['SPIP', __('Solar pump'), (int) $totals->spip, 'var(--d-spip)'],
    ];
    $sysMax = max(1, $total, ...array_column($systems, 2));

    $topMax = max(1, (int) ($byProvince->max('total') ?? 1), (int) ($byOffice->max('total') ?? 1));

    $lipaMax = max(1.0, (float) ($lipaPeriods->max('service_area') ?? 1), (float) ($lipaPeriods->max('planted_area') ?? 1));
@endphp

<x-layouts::app :title="__('Dashboard')">
    <style>
        #ia-page {
            --d-ink: #18302e;
            --d-muted: #6b7c7a;
            --d-line: #e3e9e8;
            --d-surface: #ffffff;
            --d-soft: #f3f7f6;
            --d-ok: #1f8a70;
            --d-bad: #d9822b;
            --d-cis: #2b7a9b;
            --d-nis: #5b6fb5;
            --d-spip: #e0a526;
            --d-bar: #2b7a9b;
            --d-office: #17564e;
            --d-service: #bcd7d2;
            --d-planted: #1f8a70;
        }
        .dark #ia-page {
            --d-ink: #e8f1ef;
            --d-muted: #93a6a3;
            --d-line: rgba(255,255,255,.1);
            --d-surface: #1b2423;
            --d-soft: rgba(255,255,255,.05);
            --d-ok: #3fc2a0;
            --d-bad: #f0a04f;
            --d-cis: #55a9cb;
            --d-nis: #8a9ce0;
            --d-spip: #f0c14f;
            --d-bar: #55a9cb;
            --d-office: #3fc2a0;
            --d-service: #3b4f4c;
            --d-planted: #3fc2a0;
        }
        #ia-page .d-card { background: var(--d-surface); border: 1px solid var(--d-line); border-radius: .75rem; padding: 1.25rem; }
        #ia-page .d-title { font-size: .95rem; font-weight: 600; color: var(--d-ink); }
        #ia-page .d-note { font-size: .75rem; color: var(--d-muted); line-height: 1.4; }
        #ia-page .d-head { display: flex; align-items: baseline; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }

        /* KPI strip */
        #ia-page .d-kpis { display: grid; gap: .75rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (min-width: 640px)  { #ia-page .d-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { #ia-page .d-kpis { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
        #ia-page .d-kpi { display: block; background: var(--d-surface); border: 1px solid var(--d-line); border-radius: .75rem; padding: .9rem 1rem; text-decoration: none; }
        #ia-page a.d-kpi:hover { border-color: var(--d-cis); }
        #ia-page .d-kpi-label { font-size: .75rem; color: var(--d-muted); }
        #ia-page .d-kpi-value { font-size: 1.6rem; font-weight: 650; color: var(--d-ink); line-height: 1.2; font-variant-numeric: tabular-nums; }
        #ia-page .d-kpi-dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 999px; margin-right: .35rem; }

        /* Donut */
        #ia-page .d-donut-wrap { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        #ia-page .d-donut { position: relative; width: 9.5rem; height: 9.5rem; flex: none; }
        #ia-page .d-donut svg { width: 100%; height: 100%; transform: rotate(-90deg); }
        #ia-page .d-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        #ia-page .d-donut-center strong { font-size: 1.6rem; color: var(--d-ink); line-height: 1; }
        #ia-page .d-donut-center span { font-size: .7rem; color: var(--d-muted); margin-top: .2rem; }
        #ia-page .d-legend { display: grid; gap: .6rem; font-size: .85rem; color: var(--d-ink); }
        #ia-page .d-legend b { font-variant-numeric: tabular-nums; margin-left: .35rem; }

        /* Horizontal bars */
        #ia-page .d-row { display: grid; grid-template-columns: minmax(5rem, 9rem) 1fr 3rem; align-items: center; gap: .75rem; padding: .3rem 0; font-size: .85rem; color: var(--d-ink); }
        #ia-page .d-row-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #ia-page .d-track { height: .7rem; background: var(--d-soft); border-radius: 999px; overflow: hidden; }
        #ia-page .d-fill { height: 100%; border-radius: 999px; background: var(--d-bar); min-width: 2px; }
        #ia-page .d-val { text-align: right; font-variant-numeric: tabular-nums; color: var(--d-muted); }
        #ia-page .d-scroll { max-height: 19rem; overflow-y: auto; padding-right: .25rem; }
        #ia-page .d-sep { height: 1px; background: var(--d-line); margin: .5rem 0; }

        /* LIPA column chart */
        #ia-page .d-chart { display: flex; align-items: flex-end; gap: 1rem; height: 13rem; padding: .5rem .25rem 0; border-bottom: 1px solid var(--d-line); overflow-x: auto; }
        #ia-page .d-group { flex: 1 0 4.5rem; max-width: 8rem; height: 100%; display: flex; align-items: flex-end; justify-content: center; gap: .3rem; }
        #ia-page .d-col { width: 40%; max-width: 2rem; border-radius: .3rem .3rem 0 0; min-height: 2px; }
        #ia-page .d-xlabels { display: flex; gap: 1rem; padding: .4rem .25rem 0; overflow: hidden; }
        #ia-page .d-xlabel { flex: 1 0 4.5rem; max-width: 8rem; text-align: center; font-size: .72rem; color: var(--d-muted); line-height: 1.3; }
        #ia-page .d-key { display: flex; gap: 1rem; font-size: .75rem; color: var(--d-muted); }
        #ia-page .d-key i { display: inline-block; width: .65rem; height: .65rem; border-radius: .15rem; margin-right: .3rem; }

        #ia-page .d-table { width: 100%; font-size: .82rem; border-collapse: collapse; color: var(--d-ink); }
        #ia-page .d-table th { text-align: left; font-weight: 500; color: var(--d-muted); padding: .5rem .5rem; border-bottom: 1px solid var(--d-line); white-space: nowrap; }
        #ia-page .d-table td { padding: .5rem; border-bottom: 1px solid var(--d-line); font-variant-numeric: tabular-nums; }
        #ia-page .d-table tr:last-child td { border-bottom: 0; }
        #ia-page .d-table .r { text-align: right; }

        /* Attention + activity */
        #ia-page .d-attn { display: flex; align-items: center; justify-content: space-between; padding: .6rem 0; border-bottom: 1px solid var(--d-line); font-size: .85rem; color: var(--d-ink); }
        #ia-page .d-attn:last-of-type { border-bottom: 0; }
        #ia-page .d-pill { min-width: 2rem; text-align: center; padding: .1rem .55rem; border-radius: 999px; font-size: .78rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        #ia-page .d-pill-ok   { background: color-mix(in srgb, var(--d-ok) 15%, transparent); color: var(--d-ok); }
        #ia-page .d-pill-warn { background: color-mix(in srgb, var(--d-bad) 18%, transparent); color: var(--d-bad); }
        #ia-page .d-req { margin-top: .5rem; padding: .55rem .75rem; border: 1px solid var(--d-line); border-radius: .5rem; font-size: .85rem; color: var(--d-ink); }
        #ia-page .d-timeline { position: relative; padding-left: 1.1rem; border-left: 2px solid var(--d-line); display: grid; gap: .9rem; }
        #ia-page .d-event { position: relative; }
        #ia-page .d-event::before { content: ""; position: absolute; left: -1.5rem; top: .35rem; width: .55rem; height: .55rem; border-radius: 999px; background: var(--d-cis); border: 2px solid var(--d-surface); box-sizing: content-box; }
        #ia-page .d-event.d-created::before { background: var(--d-ok); }
        #ia-page .d-event.d-deleted::before, #ia-page .d-event.d-archived::before { background: var(--d-bad); }
        #ia-page .d-event a { color: var(--d-ink); font-size: .85rem; font-weight: 500; text-decoration: none; }
        #ia-page .d-event a:hover { text-decoration: underline; }
        #ia-page .d-link { font-size: .8rem; color: var(--d-cis); text-decoration: none; }
        #ia-page .d-link:hover { text-decoration: underline; }
        #ia-page .d-empty { font-size: .85rem; color: var(--d-muted); }
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
            <div class="d-card d-empty">{{ __('Your role has no dashboard widgets yet.') }}</div>
        @else
            {{-- Headline numbers --}}
            <div class="d-kpis">
                <a href="{{ route('ia-profiles.index') }}" class="d-kpi" wire:navigate>
                    <div class="d-kpi-label">{{ __('IA profiles') }}</div>
                    <div class="d-kpi-value">{{ $n($total) }}</div>
                </a>
                <div class="d-kpi">
                    <div class="d-kpi-label"><span class="d-kpi-dot" style="background: var(--d-ok)"></span>{{ __('Operational') }}</div>
                    <div class="d-kpi-value">{{ $n($operational) }}</div>
                </div>
                <div class="d-kpi">
                    <div class="d-kpi-label"><span class="d-kpi-dot" style="background: var(--d-bad)"></span>{{ __('Non-operational') }}</div>
                    <div class="d-kpi-value">{{ $n($nonOp) }}</div>
                </div>
                <div class="d-kpi">
                    <div class="d-kpi-label">{{ __('Service area (ha)') }}</div>
                    <div class="d-kpi-value">{{ $n($totals->service_area, 2) }}</div>
                </div>
                <div class="d-kpi">
                    <div class="d-kpi-label">{{ __('IA members') }}</div>
                    <div class="d-kpi-value">{{ $n($totals->members) }}</div>
                </div>
                <a href="{{ route('ia-profiles.archived') }}" class="d-kpi" wire:navigate>
                    <div class="d-kpi-label">{{ __('Archived') }}</div>
                    <div class="d-kpi-value">{{ $n($archivedCount) }}</div>
                </a>
            </div>

            {{-- Status donut + systems --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="d-card">
                    <div class="d-head"><h3 class="d-title">{{ __('Operational status') }}</h3></div>
                    <div class="d-donut-wrap">
                        <div class="d-donut" role="img" aria-label="{{ __(':pct% operational', ['pct' => $opPct]) }}">
                            <svg viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--d-bad)" stroke-width="4" @if ($total === 0) stroke-opacity=".25" @endif />
                                @if ($total > 0)
                                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--d-ok)" stroke-width="4"
                                            stroke-dasharray="{{ $opPct }} {{ 100 - $opPct }}" />
                                @endif
                            </svg>
                            <div class="d-donut-center"><strong>{{ $opPct }}%</strong><span>{{ __('operational') }}</span></div>
                        </div>
                        <div class="d-legend">
                            <div><span class="d-kpi-dot" style="background: var(--d-ok)"></span>{{ __('Operational') }}<b>{{ $n($operational) }}</b></div>
                            <div><span class="d-kpi-dot" style="background: var(--d-bad)"></span>{{ __('Non-operational') }}<b>{{ $n($nonOp) }}</b></div>
                        </div>
                    </div>
                </section>

                <section class="d-card">
                    <div class="d-head"><h3 class="d-title">{{ __('Irrigation systems') }}</h3></div>
                    @foreach ($systems as [$code, $label, $count, $color])
                        <div class="d-row" style="grid-template-columns: minmax(7rem, 11rem) 1fr 3rem">
                            <div class="d-row-label"><strong>{{ $code }}</strong> <span style="color: var(--d-muted)">{{ $label }}</span></div>
                            <div class="d-track"><div class="d-fill" style="width: {{ round(($count / $sysMax) * 100) }}%; background: {{ $color }}"></div></div>
                            <div class="d-val">{{ $n($count) }}</div>
                        </div>
                    @endforeach
                    <p class="d-note" style="margin-top: .75rem">{{ __('An IA can have more than one system, so the counts can add up to more than the total.') }}</p>
                </section>
            </div>

            {{-- Province / office --}}
            <section class="d-card">
                <div class="d-head">
                    <h3 class="d-title">{{ $regionWide ? __('IAs per office and province') : __('IAs per province') }}</h3>
                </div>
                <div class="d-scroll">
                    @foreach ($byOffice as $row)
                        <div class="d-row">
                            <div class="d-row-label" title="{{ $row->label }}"><strong>{{ $row->label }}</strong></div>
                            <div class="d-track"><div class="d-fill" style="width: {{ round(($row->total / $topMax) * 100) }}%; background: var(--d-office)"></div></div>
                            <div class="d-val">{{ $n($row->total) }}</div>
                        </div>
                    @endforeach
                    @if ($byOffice->isNotEmpty() && $byProvince->isNotEmpty())<div class="d-sep"></div>@endif
                    @forelse ($byProvince as $row)
                        <div class="d-row">
                            <div class="d-row-label" title="{{ $row->label }}">{{ $row->label }}</div>
                            <div class="d-track"><div class="d-fill" style="width: {{ round(($row->total / $topMax) * 100) }}%"></div></div>
                            <div class="d-val">{{ $n($row->total) }}</div>
                        </div>
                    @empty
                        <p class="d-empty">{{ __('No IA profiles yet.') }}</p>
                    @endforelse
                </div>
            </section>

            {{-- LIPA --}}
            <section class="d-card">
                <div class="d-head">
                    <h3 class="d-title">{{ __('LIPA by crop year and season') }}</h3>
                    <div class="d-key">
                        <span><i style="background: var(--d-service)"></i>{{ __('Service area') }}</span>
                        <span><i style="background: var(--d-planted)"></i>{{ __('Planted area') }}</span>
                    </div>
                </div>

                @if ($lipaPeriods->isEmpty())
                    <p class="d-empty">{{ __('No LIPA records yet.') }}</p>
                @else
                    <div class="d-chart" role="img" aria-label="{{ __('Service area versus planted area per period') }}">
                        @foreach ($lipaPeriods as $p)
                            @php
                                $sH = round(((float) $p->service_area / $lipaMax) * 100);
                                $pH = round(((float) $p->planted_area / $lipaMax) * 100);
                            @endphp
                            <div class="d-group">
                                <div class="d-col" style="height: {{ $sH }}%; background: var(--d-service)" title="{{ __('Service area') }}: {{ $n($p->service_area, 2) }} ha"></div>
                                <div class="d-col" style="height: {{ $pH }}%; background: var(--d-planted)" title="{{ __('Planted area') }}: {{ $n($p->planted_area, 2) }} ha"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-xlabels">
                        @foreach ($lipaPeriods as $p)
                            <div class="d-xlabel">CY {{ $p->crop_year }}<br>{{ \App\Models\IaLipaEntry::SEASONS[$p->season] ?? $p->season }}</div>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto" style="margin-top: 1.25rem">
                        <table class="d-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Period') }}</th>
                                    <th class="r">{{ __('IAs') }}</th>
                                    <th class="r">{{ __('Records') }}</th>
                                    <th class="r">{{ __('Service area (ha)') }}</th>
                                    <th class="r">{{ __('Planted area (ha)') }}</th>
                                    <th style="min-width: 9rem">{{ __('Planted vs service') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lipaPeriods as $p)
                                    @php $pct = (float) $p->service_area > 0 ? min(100, round(((float) $p->planted_area / (float) $p->service_area) * 100)) : 0; @endphp
                                    <tr>
                                        <td style="font-weight: 500">CY {{ $p->crop_year }} · {{ \App\Models\IaLipaEntry::SEASONS[$p->season] ?? $p->season }}</td>
                                        <td class="r">{{ $n($p->ias) }}</td>
                                        <td class="r">{{ $n($p->lots) }}</td>
                                        <td class="r">{{ $n($p->service_area, 2) }}</td>
                                        <td class="r">{{ $n($p->planted_area, 2) }}</td>
                                        <td>
                                            <div class="d-row" style="grid-template-columns: 1fr 2.5rem; padding: 0">
                                                <div class="d-track"><div class="d-fill" style="width: {{ $pct }}%; background: var(--d-planted)"></div></div>
                                                <div class="d-val">{{ $pct }}%</div>
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
                <section class="d-card">
                    <div class="d-head"><h3 class="d-title">{{ __('Needs attention') }}</h3></div>
                    @foreach ($attention as $item)
                        <div class="d-attn">
                            <span>{{ $item['label'] }}</span>
                            <span class="d-pill {{ $item['value'] > 0 ? 'd-pill-warn' : 'd-pill-ok' }}">{{ $n($item['value']) }}</span>
                        </div>
                    @endforeach

                    <div class="d-head" style="margin-top: 1.5rem; margin-bottom: .25rem">
                        <h4 class="d-title" style="font-size: .85rem">{{ $isApprover ? __('Deletion requests to decide') : __('Your pending deletion requests') }}</h4>
                        <a href="{{ route('ia-profiles.deletion-requests.index') }}" class="d-link" wire:navigate>{{ __('Open') }} ({{ $n($pendingCount) }})</a>
                    </div>
                    @forelse ($pendingRequests as $req)
                        <div class="d-req">
                            <div style="font-weight: 500">{{ $req->profile?->ia_name }}</div>
                            <div class="d-note">{{ $req->requester?->name }} · {{ $req->created_at?->diffForHumans() }}</div>
                        </div>
                    @empty
                        @if ($isApprover)<p class="d-empty" style="margin-top: .5rem">{{ __('Nothing is waiting for your decision.') }}</p>@endif
                    @endforelse
                </section>

                {{-- Recent activity --}}
                <section class="d-card">
                    <div class="d-head"><h3 class="d-title">{{ __('Recent activity') }}</h3></div>
                    @if ($activity->isEmpty())
                        <p class="d-empty">{{ __('No activity recorded yet.') }}</p>
                    @else
                        <div class="d-timeline">
                            @foreach ($activity as $item)
                                <div class="d-event d-{{ $item->action }}">
                                    <a href="{{ route('ia-profiles.show', $item->entity_id) }}" wire:navigate>{{ $item->description }}</a>
                                    <div class="d-note">{{ $item->user?->name ?? __('System') }} · {{ $item->created_at?->diffForHumans() }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        @endunless
    </div>
</x-layouts::app>