{{-- One supply: its fields and the tickets it used. Shown in the popup of the supplements list and, with a layout round it, as a page. --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            {{ __('supplements.supply_date') }}: {{ $supplement->supply_day }}
            <span class="badge {{ $supplement->mode === 'manual' ? 'badge-warning' : 'badge-info' }} ml-1">{{ __('supplements.mode_' . $supplement->mode) }}</span>
            @if ($supplement->deleted_at)
                <span class="badge badge-danger ml-1">{{ __('supplements.deleted_at') }}: {{ $supplement->deleted_at }}</span>
            @endif
        </h3>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <tr><th style="width: 220px">{{ __('supplements.supplied_at') }}</th><td>{{ $supplement->supplied_at }}</td></tr>
            <tr><th>{{ __('supplements.nozzle_id') }}</th><td>{{ $nozzle }}</td></tr>
            <tr><th>{{ __('supplements.fuel_type') }}</th><td>{{ $fuels[$supplement->fuel_type] ?? $supplement->fuel_type }}</td></tr>
            @if (!empty($card))
                <tr><th>{{ __('supplements.card') }}</th><td>{{ $card }}</td></tr>
            @endif
            <tr><th>{{ __('supplements.preset_kg') }}</th><td>{{ number_format($supplement->preset_kg, 2) }} {{ __('supplements.kg') }}</td></tr>
            <tr><th>{{ __('supplements.density') }}</th><td>{{ $supplement->density !== null ? rtrim(rtrim(number_format($supplement->density, 4, '.', ''), '0'), '.') : '-' }}</td></tr>
            @can('costs.view')
                <tr><th>{{ __('supplements.cost') }}</th><td>{{ $supplement->cost !== null ? number_format($supplement->cost, 4) : '-' }}</td></tr>
            @endcan
            <tr><th>{{ __('supplements.out_amount') }}</th><td>{{ number_format($supplement->out_amount_kg, 2) }} {{ __('supplements.kg') }} / {{ number_format($supplement->out_amount_lt, 2) }} {{ __('supplements.liter') }}</td></tr>
            <tr>
                <th>{{ __('supplements.remainder') }}</th>
                <td>
                    @if ($supplement->remainder)
                        {{ $supplement->remainder->ticket_barcode }} ({{ number_format($supplement->remainder->amount_kg, 2) }} {{ __('supplements.kg') }})
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr><th>{{ __('supplements.comment') }}</th><td>{{ $supplement->comment }}</td></tr>
            @if ($supplement->confirmed_at)
                <tr><th>{{ __('supplements.sale_confirmed') }}</th><td>{{ $supplement->confirmed_at }}</td></tr>
            @endif
        </table>

        <h6 class="font-weight-bold mt-3">{{ __('supplements.supply_tickets') }} ({{ $ticketCount }})</h6>
        @if ($tickets->isNotEmpty())
            <table class="table table-sm">
                <thead>
                <tr>
                    <th>{{ __('supplements.barcode') }}</th>
                    <th class="text-right">{{ __('supplements.amount_kg') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($tickets as $t)
                    <tr>
                        <td>{{ $t->barcode }}</td>
                        <td class="text-right">{{ number_format(!is_null($t->spec) ? config('ticket_specs.' . $t->spec) : $t->amount_kg, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if ($ticketCount > $tickets->count())
                <div class="text-muted">{{ __('supplements.sale_tickets_first', ['count' => $tickets->count()]) }}</div>
            @endif
        @endif
    </div>
</div>
