{{-- Sales of the last 14 days: stacked bars (kg) of ticket, spot and card sales; the confirmation of the day shows in the bar (solid = confirmed,
     faded = not confirmed) and in the strip under it. --}}
@php
    $w = 640; $h = 280; $left = 56; $right = 10; $top = 14; $bottom = 62;
    $plotW = $w - $left - $right; $plotH = $h - $top - $bottom;
    $totals = collect($salesByDay)->map(function ($d) { return max(0, $d->ticket) + max(0, $d->spot) + max(0, $d->card); });
    $max = App\Support\Dashboard\DashboardData::niceMax($totals->max() ?: 0);
    $y = function ($kg) use ($top, $plotH, $max) { return round($top + $plotH - max(0, min($kg, $max)) / $max * $plotH, 1); };
    $slot = $plotW / count($salesByDay);
    $barW = $slot * 0.62;
    $types = ['ticket', 'spot', 'card'];
@endphp
<div class="card" id="dash-sales">
    <div class="card-header"><h3 class="card-title">{{ __('dashboard.dash_sales_days', ['days' => count($salesByDay)]) }}</h3></div>
    <div class="card-body">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="dash-chart" role="img" aria-label="{{ __('dashboard.dash_sales_days', ['days' => count($salesByDay)]) }}">
            @for ($i = 0; $i <= 4; $i++)
                @php $v = $max / 4 * $i; @endphp
                <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" class="dash-grid"/>
                <text x="{{ $left - 6 }}" y="{{ $y($v) + 4 }}" text-anchor="end" class="dash-axis">{{ number_format($v, 0) }}</text>
            @endfor
            @foreach ($salesByDay as $i => $d)
                @php
                    $x = $left + $slot * $i + ($slot - $barW) / 2;
                    $base = 0;
                    $mid = round($x + $barW / 2, 1);
                @endphp
                @foreach ($types as $type)
                    @if ($d->$type > 0)
                        <rect x="{{ round($x, 1) }}" y="{{ $y($base + $d->$type) }}" width="{{ round($barW, 1) }}" height="{{ round($y($base) - $y($base + $d->$type), 1) }}"
                              class="dash-{{ $type }} {{ $d->status === 'confirmed' ? '' : 'dash-unconfirmed' }}">
                            <title>{{ $d->day }}: {{ __('dashboard.dash_type_' . $type) }} {{ number_format($d->$type, 2) }} {{ __('dashboard.kg') }}</title>
                        </rect>
                        @php $base += $d->$type; @endphp
                    @endif
                @endforeach
                <rect x="{{ round($x, 1) }}" y="{{ $h - $bottom + 6 }}" width="{{ round($barW, 1) }}" height="6" class="dash-status-{{ $d->status }}">
                    <title>{{ $d->day }}: {{ __('dashboard.dash_status_' . $d->status) }}</title>
                </rect>
                <text x="{{ $mid }}" y="{{ $h - $bottom + 26 }}" text-anchor="middle" class="dash-axis">{{ substr($d->day, 8, 2) }}</text>
                @if ($i === 0 || substr($d->day, 8, 2) === '01')
                    <text x="{{ $mid }}" y="{{ $h - $bottom + 40 }}" text-anchor="middle" class="dash-axis">{{ substr($d->day, 5, 2) }}</text>
                @endif
            @endforeach
        </svg>
        <div class="small text-muted dash-legend">
            @foreach ($types as $type)
                <span class="mr-3"><span class="dash-swatch dash-{{ $type }}"></span> {{ __('dashboard.dash_type_' . $type) }}</span>
            @endforeach
            <span class="mr-3"><span class="dash-swatch dash-status-confirmed"></span> {{ __('dashboard.dash_status_confirmed') }}</span>
            <span class="mr-3"><span class="dash-swatch dash-status-open"></span> {{ __('dashboard.dash_status_open') }}</span>
            <span><span class="dash-swatch dash-status-none"></span> {{ __('dashboard.dash_status_none') }}</span>
        </div>
    </div>
</div>
