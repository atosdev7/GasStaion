{{-- The date of the page, the dates still waiting (each shows sales / supplements not confirmed) and what is already confirmed --}}
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <form method="GET" action="{{ route($route) }}" class="form-inline">
                <label for="f_date" class="mr-2">{{ __('confirm.date') }}</label>
                <input id="f_date" name="date" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" class="form-control mr-2">
                <button type="submit" class="btn btn-primary">{{ __('confirm.show') }}</button>
            </form>
            {{-- the waiting dates stand on the right of the same row --}}
            <div class="text-right">
                <span class="text-muted mr-2">{{ __('confirm.waiting') }}:</span>
                @forelse ($waiting as $d => $counts)
                    <a href="{{ route($route, ['date' => $d]) }}" class="badge {{ $d === $date ? 'badge-primary' : 'badge-secondary' }} p-2 ml-1">
                        {{ $d }} ({{ $counts['sales'] }} / {{ $counts['supplements'] }} / {{ $counts['tickets'] }}@if ($counts['deleted']) / {{ $counts['deleted'] }} {{ __('confirm.deleted_short') }}@endif)
                    </a>
                @empty
                    <span class="text-muted">{{ __('confirm.none_waiting') }}</span>
                @endforelse
                <small class="form-text text-muted">{{ __('confirm.counts_hint') }}</small>
            </div>
        </div>
        @if ($confirmedSales || $confirmedSupplements || $confirmedTickets)
            <small class="form-text text-muted">{{ __('confirm.already', ['sales' => $confirmedSales, 'supplements' => $confirmedSupplements, 'tickets' => $confirmedTickets]) }}</small>
        @endif
    </div>
</div>
