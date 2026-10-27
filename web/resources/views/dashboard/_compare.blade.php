{{-- Today against yesterday: kg sold, amount, and kg supplied against kg sold. --}}
@php
    $tiles = [
        ['label' => __('dashboard.dash_sold_kg'), 'now' => $compare->today->kg, 'before' => $compare->yesterday->kg, 'change' => $compare->kgChange, 'unit' => __('dashboard.kg'), 'own' => $compare->today->own],
        ['label' => __('dashboard.dash_sales_amount'), 'now' => $compare->today->amount, 'before' => $compare->yesterday->amount, 'change' => $compare->amountChange, 'unit' => ''],
        ['label' => __('dashboard.dash_supplied_kg'), 'now' => $compare->today->supplied, 'before' => $compare->yesterday->supplied, 'change' => $compare->suppliedChange, 'unit' => __('dashboard.kg')],
    ];
    $gap = $compare->today->supplied - $compare->today->kg;
@endphp
<div class="card" id="dash-compare">
    <div class="card-header"><h3 class="card-title">{{ __('dashboard.dash_today_vs_yesterday') }}</h3></div>
    <div class="card-body">
        <div class="row">
            @foreach ($tiles as $tile)
                <div class="col-md-3 col-6 mb-2">
                    <div class="text-muted">{{ $tile['label'] }}</div>
                    <div class="h4 mb-0">{{ number_format($tile['now'], 2) }} {{ $tile['unit'] }}</div>
                    @if (!empty($tile['own']))
                        <div class="small text-muted">{{ __('dashboard.dash_own_use', ['kg' => number_format($tile['own'], 2)]) }}</div>
                    @endif
                    <div class="small">
                        @if ($tile['change'] === null)
                            <span class="text-muted">&ndash;</span>
                        @elseif ($tile['change'] >= 0)
                            <span class="text-success dash-up">&#9650; {{ $tile['change'] }}%</span>
                        @else
                            <span class="text-danger dash-down">&#9660; {{ abs($tile['change']) }}%</span>
                        @endif
                        <span class="text-muted">{{ __('dashboard.dash_yesterday', ['value' => number_format($tile['before'], 2)]) }}</span>
                    </div>
                </div>
            @endforeach
            <div class="col-md-3 col-6 mb-2">
                <div class="text-muted">{{ __('dashboard.dash_supplied_vs_sold') }}</div>
                <div class="h4 mb-0">{{ number_format($compare->today->supplied, 2) }} / {{ number_format($compare->today->kg, 2) }}</div>
                <div class="small {{ $gap < -0.004 ? 'text-warning' : 'text-muted' }}">
                    {{ __('dashboard.dash_not_supplied_yet', ['kg' => number_format(max(0, -$gap), 2)]) }}
                </div>
            </div>
        </div>
    </div>
</div>
