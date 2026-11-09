{{-- Loading skeleton. Params: $id, $grid (css class), $cols (int), $rows (int, default 6) --}}
<div id="{{ $id }}" aria-hidden="true">
    <div class="rt-skel-row {{ $grid }} is-head">
        @for ($i = 0; $i < $cols; $i++)
            <div class="ut-skel-bar" style="height: 0.75rem; width: 60%"></div>
        @endfor
    </div>

    @for ($row = 0; $row < ($rows ?? 6); $row++)
        <div class="rt-skel-row {{ $grid }}">
            <div>
                <div class="ut-skel-bar" style="height: 0.9rem; width: 70%"></div>
                <div class="ut-skel-bar" style="height: 0.65rem; width: 40%; margin-top: 0.4rem"></div>
            </div>
            @for ($i = 1; $i < $cols - 1; $i++)
                <div class="ut-skel-bar" style="height: 0.9rem; width: {{ 45 + (($i * 17 + $row * 11) % 40) }}%"></div>
            @endfor
            <div class="ut-skel-bar" style="height: 1.6rem; width: 6rem; margin-left: auto"></div>
        </div>
    @endfor
</div>
