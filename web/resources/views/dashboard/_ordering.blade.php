{{-- What to order: per fuel type, the free fuel (tanks less what is owed on tickets sold and not supplied) and the days it lasts at the pace of the last 30 days. --}}
@php($cover = App\Support\Reports\StockOrdering::WINDOWS[1])
<div class="card" id="dash-ordering">
    <div class="card-header">
        <h3 class="card-title">{{ __('dashboard.dash_ordering') }}</h3>
        @can('reports.view')
            <div class="card-tools"><a href="{{ route('reports.ordering') }}">{{ __('dashboard.dash_ordering_report') }}</a></div>
        @endcan
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0 text-nowrap">
            <thead>
            <tr>
                <th>{{ __('dashboard.fuel_type') }}</th>
                <th class="text-right">{{ __('dashboard.dash_in_tanks') }}</th>
                <th class="text-right">{{ __('dashboard.dash_owed') }}</th>
                <th class="text-right">{{ __('dashboard.dash_free') }}</th>
                <th class="text-right">{{ __('dashboard.dash_lasts', ['days' => $cover]) }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($ordering as $r)
                @php($short = $r->cover[$cover] !== null && $r->cover[$cover] < $lowCoverDays)
                <tr class="ordering-{{ $r->fuel_type }}">
                    <td>{{ $fuels[$r->fuel_type] ?? $r->fuel_type }}</td>
                    <td class="text-right">{{ number_format($r->tanks, 2) }}</td>
                    <td class="text-right">{{ number_format($r->owed, 2) }}</td>
                    <td class="text-right font-weight-bold {{ $r->free < 0 ? 'text-danger' : '' }}">{{ number_format($r->free, 2) }}</td>
                    <td class="text-right {{ $short ? 'text-danger font-weight-bold' : '' }}">{{ $r->cover[$cover] === null ? '-' : number_format($r->cover[$cover], 1) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted small">{{ __('dashboard.dash_ordering_hint', ['days' => $lowCoverDays]) }}</div>
</div>