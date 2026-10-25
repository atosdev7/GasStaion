{{-- The date of the page, the dates still waiting and what is already confirmed. A date shows only the count of this page (the area: fuel, tickets, sales, supplements or debts;
     the overview and the summary show all of them added up). --}}
@php
    $keys = !empty($area) ? [$area] : ['fuel', 'tickets', 'sales', 'supplements', 'debts'];
    $showDeleted = empty($area) || $area === 'sales';   // the deleted sales wait to be erased with the sales
    $dates = [];
    foreach ($waiting as $d => $counts) {
        $n = array_sum(array_intersect_key($counts, array_flip($keys)));
        $gone = $showDeleted ? $counts['deleted'] : 0;
        if ($n || $gone) {
            $dates[$d] = [$n, $gone];
        }
    }
    // the latest five dates are suggested (the waiting dates are newest first); an older one is chosen with the date box
    // on the summary a waiting date leads to the overview of that date (what is waiting, and where); on the other pages it stays on the page
    $badgeRoute = $route === 'confirmations.summary' ? 'confirmations.overview' : $route;
    $older = max(0, count($dates) - 5);
    $dates = array_slice($dates, 0, 5, true);
@endphp
<div class="card">
    <div class="card-body">
        <div>
            <form method="GET" action="{{ route($route) }}" class="form-inline">
                <label for="f_date" class="mr-2">{{ __('confirm.date') }}</label>
                <input id="f_date" name="date" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" class="form-control">
                <button type="submit" class="btn btn-primary ml-2">{{ __('confirm.show') }}</button>
            </form>
            {{-- the waiting dates stand under the date, the latest five of them --}}
            <div class="mt-3">
                <span class="text-muted mr-2">{{ __('confirm.waiting') }}:</span>
                @forelse ($dates as $d => $shown)
                    <a href="{{ route($badgeRoute, ['date' => $d]) }}" class="badge {{ $d === $date ? 'badge-primary' : 'badge-secondary' }} p-2 ml-1">
                        {{ $d }} ({{ $shown[0] }}@if ($shown[1]) / {{ $shown[1] }} {{ __('confirm.deleted_short') }}@endif)
                    </a>
                @empty
                    <span class="text-muted">{{ __('confirm.none_waiting') }}</span>
                @endforelse
                @if ($older)
                    <span class="text-muted ml-2">{{ __('confirm.older_dates', ['count' => $older]) }}</span>
                @endif
                <small class="form-text text-muted">{{ empty($area) ? __('confirm.counts_hint_all') : __('confirm.counts_hint') }}</small>
            </div>
        </div>
        @if ($confirmedSales || $confirmedSupplements || $confirmedTickets || $confirmedFuel || $confirmedDebts)
            <small class="form-text text-muted">{{ __('confirm.already', ['sales' => $confirmedSales, 'supplements' => $confirmedSupplements, 'tickets' => $confirmedTickets, 'fuel' => $confirmedFuel, 'debts' => $confirmedDebts]) }}</small>
        @endif
    </div>
</div>
