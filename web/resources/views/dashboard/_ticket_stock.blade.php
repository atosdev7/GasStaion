{{-- Ticket stock: two bars per fuel type and spec, the blank tickets in stock and the published ones ready to sell. --}}
@php
    $w = 520; $h = 280; $left = 50; $right = 10; $top = 18; $bottom = 58;
    $plotW = $w - $left - $right; $plotH = $h - $top - $bottom;
    $max = App\Support\Dashboard\DashboardData::niceMax(collect($ticketStock)->map(function ($s) { return max($s->blank, $s->ready); })->max() ?: 0);
    $y = function ($n) use ($top, $plotH, $max) { return round($top + $plotH - max(0, min($n, $max)) / $max * $plotH, 1); };
    $slot = $plotW / max(1, count($ticketStock));
    $barW = min(22, $slot * 0.34);
@endphp
<div class="card" id="dash-tickets">
    <div class="card-header"><h3 class="card-title">{{ __('dashboard.dash_ticket_stock') }}</h3></div>
    <div class="card-body">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="dash-chart" role="img" aria-label="{{ __('dashboard.dash_ticket_stock') }}">
            @for ($i = 0; $i <= 4; $i++)
                @php $v = $max / 4 * $i; @endphp
                <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" class="dash-grid"/>
                <text x="{{ $left - 6 }}" y="{{ $y($v) + 4 }}" text-anchor="end" class="dash-axis">{{ number_format($v, 0) }}</text>
            @endfor
            @foreach ($ticketStock as $i => $s)
                @php $mid = $left + $slot * $i + $slot / 2; @endphp
                <rect x="{{ round($mid - $barW - 1, 1) }}" y="{{ $y($s->blank) }}" width="{{ round($barW, 1) }}" height="{{ round($y(0) - $y($s->blank), 1) }}" class="dash-blank">
                    <title>{{ $fuels[$s->fuel_type] ?? $s->fuel_type }} {{ $s->kg }} {{ __('dashboard.kg') }}: {{ __('dashboard.dash_blank') }} {{ $s->blank }}</title>
                </rect>
                <rect x="{{ round($mid + 1, 1) }}" y="{{ $y($s->ready) }}" width="{{ round($barW, 1) }}" height="{{ round($y(0) - $y($s->ready), 1) }}" class="dash-ready">
                    <title>{{ $fuels[$s->fuel_type] ?? $s->fuel_type }} {{ $s->kg }} {{ __('dashboard.kg') }}: {{ __('dashboard.dash_ready') }} {{ $s->ready }}</title>
                </rect>
                <text x="{{ round($mid - $barW / 2 - 1, 1) }}" y="{{ $y($s->blank) - 4 }}" text-anchor="middle" class="dash-value">{{ $s->blank }}</text>
                <text x="{{ round($mid + $barW / 2 + 1, 1) }}" y="{{ $y($s->ready) - 4 }}" text-anchor="middle" class="dash-value">{{ $s->ready }}</text>
                <text x="{{ round($mid, 1) }}" y="{{ $h - $bottom + 16 }}" text-anchor="middle" class="dash-label">{{ $s->kg }} {{ __('dashboard.kg') }}</text>
                <text x="{{ round($mid, 1) }}" y="{{ $h - $bottom + 31 }}" text-anchor="middle" class="dash-axis">{{ $fuels[$s->fuel_type] ?? $s->fuel_type }}</text>
            @endforeach
        </svg>
        <div class="small text-muted dash-legend">
            <span class="mr-3"><span class="dash-swatch dash-blank"></span> {{ __('dashboard.dash_blank') }}</span>
            <span><span class="dash-swatch dash-ready"></span> {{ __('dashboard.dash_ready') }}</span>
        </div>
    </div>
</div>
