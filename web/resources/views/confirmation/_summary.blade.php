{{-- The figures of the whole date, confirmed or not; deleted sales are not counted. Sections: Sales, Supplements, Remainders. --}}
@php($money = function ($n) { return number_format($n, 2); })
{{-- Sales: ticket sales (a row per fuel type, with the tickets of each spec); spot sales, self use and the others each its own table with the fuel
     types side by side (the first on the left, the second on the right) --}}
<h5 class="mt-3">{{ __('confirm.sales') }}</h5>
@php($saleLabel = function ($kind) { return in_array($kind, ['ticket', 'spot', 'card'], true) ? __('menu.' . $kind . '_sales') : __('confirm.sum_kind_' . $kind); })
<h6 class="mt-3">{{ $saleLabel('ticket') }}</h6>
@php($specs = $summary['ticketSpecs'])
<div class="table-responsive">
    <table class="table table-sm text-nowrap">
        <thead>
        <tr>
            <th rowspan="2">{{ __('confirm.fuel_type') }}</th>
            <th rowspan="2" class="text-right">{{ __('confirm.sum_count') }}</th>
            <th colspan="{{ count($specs) }}" class="text-center">{{ __('confirm.tickets') }}</th>
            <th rowspan="2" class="text-right">{{ __('confirm.amount_kg') }}</th>
            <th rowspan="2" class="text-right">{{ __('confirm.total_amount') }}</th>
            <th rowspan="2" class="text-right">{{ __('confirm.paid_amount') }}</th>
            <th rowspan="2" class="text-right">{{ __('confirm.unpaid_amount') }}</th>
        </tr>
        <tr>
            @foreach ($specs as $kg)
                <th class="text-right">{{ $kg }}{{ __('confirm.kg') }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($summary['salesBy']['ticket'] as $fuel => $line)
            <tr>
                <td>{{ $fuels[$fuel] ?? $fuel }}</td>
                <td class="text-right">{{ $line['count'] }}</td>
                @foreach ($specs as $spec => $kg)
                    <td class="text-right">{{ $line['specs'][$spec] ?? 0 }}</td>
                @endforeach
                <td class="text-right">{{ $money($line['kg']) }}</td>
                <td class="text-right">{{ $money($line['total']) }}</td>
                <td class="text-right">{{ $money($line['paid']) }}</td>
                <td class="text-right">{{ $money($line['unpaid']) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        @php($lines = collect($summary['salesBy']['ticket']))
        <tr class="font-weight-bold">
            <td>{{ __('confirm.total') }}</td>
            <td class="text-right">{{ $lines->sum('count') }}</td>
            @foreach ($specs as $spec => $kg)
                <td class="text-right">{{ $lines->sum(function ($line) use ($spec) { return $line['specs'][$spec] ?? 0; }) }}</td>
            @endforeach
            <td class="text-right">{{ $money($lines->sum('kg')) }}</td>
            <td class="text-right">{{ $money($lines->sum('total')) }}</td>
            <td class="text-right">{{ $money($lines->sum('paid')) }}</td>
            <td class="text-right">{{ $money($lines->sum('unpaid')) }}</td>
        </tr>
        </tfoot>
    </table>
</div>

@foreach (['spot', 'card', 'self', 'others'] as $kind)
    <h6 class="mt-3">{{ $saleLabel($kind) }}</h6>
    <div class="table-responsive">
        <table class="table table-sm text-nowrap">
            <thead>
            <tr>
                @foreach ($summary['salesBy'][$kind] as $fuel => $line)
                    <th colspan="5" class="text-center {{ $loop->first ? '' : 'border-left' }}">{{ $fuels[$fuel] ?? $fuel }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($summary['salesBy'][$kind] as $fuel => $line)
                    <th class="text-right {{ $loop->first ? '' : 'border-left' }}">{{ __('confirm.sum_count') }}</th>
                    <th class="text-right">{{ __('confirm.amount_kg') }}</th>
                    <th class="text-right">{{ __('confirm.total_amount') }}</th>
                    <th class="text-right">{{ __('confirm.paid_amount') }}</th>
                    <th class="text-right">{{ __('confirm.unpaid_amount') }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            <tr>
                @foreach ($summary['salesBy'][$kind] as $fuel => $line)
                    <td class="text-right {{ $loop->first ? '' : 'border-left' }}">{{ $line['count'] }}</td>
                    <td class="text-right">{{ $money($line['kg']) }}</td>
                    <td class="text-right">{{ $money($line['total']) }}</td>
                    <td class="text-right">{{ $money($line['paid']) }}</td>
                    <td class="text-right">{{ $money($line['unpaid']) }}</td>
                @endforeach
            </tr>
            </tbody>
        </table>
    </div>
@endforeach

{{-- Supplements: by the kind of the tickets they used (a row each: tickets, spot, remainder, self, others), Gasoline on the left and Diesel on the right --}}
@php($fuelKeys = $summary['fuelKeys'])
<h5 class="mt-3">{{ __('confirm.supplements') }}</h5>
<div class="table-responsive">
    <table class="table table-sm text-nowrap">
        <thead>
        <tr>
            <th rowspan="2">{{ __('confirm.sum_ticket_kind') }}</th>
            @foreach ($fuelKeys as $fuel)
                <th colspan="5" class="text-center border-left">{{ $fuels[$fuel] ?? $fuel }}</th>
            @endforeach
        </tr>
        <tr>
            @foreach ($fuelKeys as $fuel)
                <th class="text-right border-left">{{ __('confirm.sum_count') }}</th>
                <th class="text-right">{{ __('confirm.preset_amount') }}</th>
                <th class="text-right">{{ __('confirm.out_amount_kg') }}</th>
                <th class="text-right">{{ __('confirm.remainder') }}</th>
                <th>{{ __('confirm.comment') }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($summary['supplementsBy'] as $kind => $byFuel)
            <tr>
                <td>{{ __('confirm.sum_kind_' . $kind) }}</td>
                @foreach ($byFuel as $fuel => $line)
                    <td class="text-right border-left">{{ $line['count'] }}</td>
                    <td class="text-right">{{ $money($line['preset']) }}</td>
                    <td class="text-right">{{ $money($line['kg']) }}</td>
                    <td class="text-right">{{ $money($line['remainder']) }}</td>
                    <td class="text-wrap">{{ $line['comment'] }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr class="font-weight-bold">
            <td>{{ __('confirm.total') }}</td>
            @foreach ($fuelKeys as $fuel)
                @php($column = collect($summary['supplementsBy'])->pluck($fuel))
                <td class="text-right border-left">{{ $column->sum('count') }}</td>
                <td class="text-right">{{ $money($column->sum('preset')) }}</td>
                <td class="text-right">{{ $money($column->sum('kg')) }}</td>
                <td class="text-right">{{ $money($column->sum('remainder')) }}</td>
                <td></td>
            @endforeach
        </tr>
        </tfoot>
    </table>
</div>

{{-- Remainders: made on the date and used up on it, the fuel types side by side --}}
<h5 class="mt-3">{{ __('menu.remainders') }}</h5>
<div class="table-responsive">
    <table class="table table-sm text-nowrap">
        <thead>
        <tr>
            @foreach ($summary['remaindersBy'] as $fuel => $line)
                <th colspan="4" class="text-center {{ $loop->first ? '' : 'border-left' }}">{{ $fuels[$fuel] ?? $fuel }}</th>
            @endforeach
        </tr>
        <tr>
            @foreach ($summary['remaindersBy'] as $fuel => $line)
                <th class="text-right {{ $loop->first ? '' : 'border-left' }}">{{ __('confirm.sum_made') }}</th>
                <th class="text-right">{{ __('confirm.sum_made') }} ({{ __('confirm.kg') }})</th>
                <th class="text-right">{{ __('confirm.sum_used') }}</th>
                <th class="text-right">{{ __('confirm.sum_used') }} ({{ __('confirm.kg') }})</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        <tr>
            @foreach ($summary['remaindersBy'] as $fuel => $line)
                <td class="text-right {{ $loop->first ? '' : 'border-left' }}">{{ $line['made'] }}</td>
                <td class="text-right">{{ $money($line['made_kg']) }}</td>
                <td class="text-right">{{ $line['used'] }}</td>
                <td class="text-right">{{ $money($line['used_kg']) }}</td>
            @endforeach
        </tr>
        </tbody>
    </table>
</div>
