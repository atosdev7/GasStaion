{{-- Tank stock: a vertical bar per tank (its capacity is the outline, the low-level mark is the red tick). --}}
@php
    $w = 520; $h = 280; $left = 56; $right = 10; $top = 18; $bottom = 58;
    $plotW = $w - $left - $right; $plotH = $h - $top - $bottom;
    $max = App\Support\DashboardData::niceMax($tanks->map(function ($t) { return max($t->current, $t->capacity ?: 0); })->max() ?: 0);
    $y = function ($kg) use ($top, $plotH, $max) { return round($top + $plotH - max(0, min($kg, $max)) / $max * $plotH, 1); };
    $slot = $tanks->count() ? $plotW / $tanks->count() : $plotW;
    $barW = min(64, $slot * 0.5);
@endphp
<div class="card" id="dash-tanks">
    <div class="card-header"><h3 class="card-title">{{ __('dashboard.title_tanks') }}</h3></div>
    <div class="card-body">
        @if ($tanks->isEmpty())
            <p class="text-muted mb-0">{{ __('dashboard.no_data') }}</p>
        @else
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="dash-chart" role="img" aria-label="{{ __('dashboard.title_tanks') }}">
                @for ($i = 0; $i <= 4; $i++)
                    @php $v = $max / 4 * $i; @endphp
                    <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" class="dash-grid"/>
                    <text x="{{ $left - 6 }}" y="{{ $y($v) + 4 }}" text-anchor="end" class="dash-axis">{{ number_format($v, 0) }}</text>
                @endfor
                @foreach ($tanks as $i => $t)
                    @php
                        $x = $left + $slot * $i + ($slot - $barW) / 2;
                        $low = $t->current < 0 || ($t->percent !== null && $t->percent <= $lowPercent);
                    @endphp
                    @if ($t->capacity)
                        <rect x="{{ round($x, 1) }}" y="{{ $y($t->capacity) }}" width="{{ round($barW, 1) }}" height="{{ round($y(0) - $y($t->capacity), 1) }}" class="dash-capacity"/>
                        <line x1="{{ round($x - 4, 1) }}" x2="{{ round($x + $barW + 4, 1) }}" y1="{{ $y($t->capacity * $lowPercent / 100) }}" y2="{{ $y($t->capacity * $lowPercent / 100) }}" class="dash-lowmark"/>
                    @endif
                    <rect x="{{ round($x, 1) }}" y="{{ $y($t->current) }}" width="{{ round($barW, 1) }}" height="{{ round($y(0) - $y($t->current), 1) }}"
                          class="dash-tank-bar {{ $low ? 'dash-low' : '' }}"/>
                    <text x="{{ round($x + $barW / 2, 1) }}" y="{{ $y($t->current) - 5 }}" text-anchor="middle" class="dash-value">{{ number_format($t->current, 0) }}@if ($t->percent !== null) ({{ $t->percent }}%)@endif</text>
                    <text x="{{ round($x + $barW / 2, 1) }}" y="{{ $h - $bottom + 16 }}" text-anchor="middle" class="dash-label">{{ $t->tank->name }}</text>
                    <text x="{{ round($x + $barW / 2, 1) }}" y="{{ $h - $bottom + 31 }}" text-anchor="middle" class="dash-axis">{{ $fuels[$t->tank->fuel_type] ?? $t->tank->fuel_type }}</text>
                @endforeach
            </svg>
            <div class="small text-muted">{{ __('dashboard.dash_tank_legend', ['percent' => $lowPercent]) }}</div>
        @endif
    </div>
</div>
