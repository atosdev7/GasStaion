{{-- Tank level trend: one line per tank over the last 14 days; the dashed line of the same colour is the tank's low-level mark. --}}
@php
    $w = 640; $h = 280; $left = 56; $right = 10; $top = 14; $bottom = 40;
    $plotW = $w - $left - $right; $plotH = $h - $top - $bottom;
    $days = $tankTrend->days;
    $values = [0];
    foreach ($tankTrend->lines as $line) {
        $values = array_merge($values, $line->levels);
        if ($line->capacity) {
            $values[] = $line->capacity * $lowPercent / 100;
        }
    }
    $max = App\Support\DashboardData::niceMax(max($values));
    $y = function ($kg) use ($top, $plotH, $max) { return round($top + $plotH - max(0, min($kg, $max)) / $max * $plotH, 1); };
    $step = count($days) > 1 ? $plotW / (count($days) - 1) : 0;
    $colors = ['#007bff', '#fd7e14', '#28a745', '#6f42c1', '#dc3545', '#20c997', '#6c757d', '#e83e8c'];
@endphp
<div class="card" id="dash-trend">
    <div class="card-header"><h3 class="card-title">{{ __('dashboard.dash_tank_trend', ['days' => count($days)]) }}</h3></div>
    <div class="card-body">
        @if (empty($tankTrend->lines))
            <p class="text-muted mb-0">{{ __('dashboard.no_data') }}</p>
        @else
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="dash-chart" role="img" aria-label="{{ __('dashboard.dash_tank_trend', ['days' => count($days)]) }}">
                @for ($i = 0; $i <= 4; $i++)
                    @php $v = $max / 4 * $i; @endphp
                    <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" class="dash-grid"/>
                    <text x="{{ $left - 6 }}" y="{{ $y($v) + 4 }}" text-anchor="end" class="dash-axis">{{ number_format($v, 0) }}</text>
                @endfor
                @foreach ($days as $i => $day)
                    <text x="{{ round($left + $step * $i, 1) }}" y="{{ $h - $bottom + 16 }}" text-anchor="middle" class="dash-axis">{{ substr($day, 8, 2) }}</text>
                    @if ($i === 0 || substr($day, 8, 2) === '01')
                        <text x="{{ round($left + $step * $i, 1) }}" y="{{ $h - $bottom + 30 }}" text-anchor="middle" class="dash-axis">{{ substr($day, 5, 2) }}</text>
                    @endif
                @endforeach
                @foreach ($tankTrend->lines as $n => $line)
                    @php
                        $color = $colors[$n % count($colors)];
                        $points = [];
                        foreach ($line->levels as $i => $level) {
                            $points[] = round($left + $step * $i, 1) . ',' . $y($level);
                        }
                    @endphp
                    @if ($line->capacity)
                        <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($line->capacity * $lowPercent / 100) }}" y2="{{ $y($line->capacity * $lowPercent / 100) }}"
                              stroke="{{ $color }}" stroke-dasharray="5 4" stroke-width="1.2" opacity="0.8" class="dash-lowline"/>
                    @endif
                    <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="{{ $color }}" stroke-width="2" class="dash-line"/>
                @endforeach
            </svg>
            <div class="small text-muted dash-legend">
                @foreach ($tankTrend->lines as $n => $line)
                    <span class="mr-3"><span class="dash-swatch" style="background: {{ $colors[$n % count($colors)] }}"></span> {{ $line->tank->name }}</span>
                @endforeach
                <span>{{ __('dashboard.dash_trend_legend', ['percent' => $lowPercent]) }}</span>
            </div>
        @endif
    </div>
</div>
