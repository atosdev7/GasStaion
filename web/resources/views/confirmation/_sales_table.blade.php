{{-- One table of the Sales page: $sides (the parts side by side: title, rows, withFuel, detail, target) and $export (its .xlsx). Several sides share
     the one table: a heading over each, and row n holds the n-th sale of every side; one side is a plain table. A side shows the payment of
     each sale (detail 'payment') or its sale type and usage (detail 'usage'); a side with `specs` has a column for the number of tickets of each
     spec (the ticket sales: Count 5kg / 10kg / 15kg / 50kg), under a Count heading over a second heading row. --}}
@php($columns = function ($side) { return 8 + (empty($side['withFuel']) ? 0 : 1) + ($side['detail'] === 'payment' ? 1 : 2) + count($side['specs'] ?? []); })
@php($specs = count($sides) === 1 ? ($sides[0]['specs'] ?? []) : [])
@php($lines = max(array_map(function ($side) { return $side['rows']->count(); }, $sides)))
@php($many = count($sides) > 1)
@if ($showExport ?? true)
    <div class="clearfix mb-2">
        <a href="{{ $export }}" class="btn btn-outline-success btn-sm float-right"><i class="fas fa-file-excel"></i> {{ __('confirm.export_xls') }}</a>
    </div>
@endif
<div class="table-responsive">
    <table class="table table-sm table-hover text-nowrap">
        <thead>
        @if ($many)
            <tr>
                @foreach ($sides as $side)
                    <th colspan="{{ $columns($side) }}" class="text-center {{ $loop->first ? '' : 'border-left' }}">{{ $side['title'] }}</th>
                @endforeach
            </tr>
        @endif
        @if ($specs)
            @php($side = $sides[0])
            <tr>
                <th rowspan="2" style="width: 30px"><input type="checkbox" class="check-all" data-target="{{ $side['target'] }}" title="{{ __('confirm.check_all') }}"></th>
                <th rowspan="2">{{ __('confirm.time') }}</th>
                @if (!empty($side['withFuel']))<th rowspan="2">{{ __('confirm.fuel_type') }}</th>@endif
                <th rowspan="2">{{ __('confirm.customer_id') }}</th>
                <th rowspan="2">{{ __('confirm.payment_method') }}</th>
                <th colspan="{{ count($specs) }}" class="text-center">{{ __('confirm.count') }}</th>
                <th rowspan="2" class="text-right">{{ __('confirm.amount_kg') }}</th>
                <th rowspan="2" class="text-right">{{ __('confirm.total_amount') }}</th>
                <th rowspan="2" class="text-right">{{ __('confirm.paid_amount') }}</th>
                <th rowspan="2" class="text-right">{{ __('confirm.unpaid_amount') }}</th>
                <th rowspan="2" class="text-right">{{ __('confirm.actions') }}</th>
            </tr>
            <tr>
                @foreach ($specs as $kg)
                    <th class="text-right">{{ $kg }}{{ __('confirm.kg') }}</th>
                @endforeach
            </tr>
        @else
        <tr>
            @foreach ($sides as $side)
                <th style="width: 30px" class="{{ !$loop->first ? 'border-left' : '' }}"><input type="checkbox" class="check-all" data-target="{{ $side['target'] }}" title="{{ __('confirm.check_all') }}"></th>
                <th>{{ __('confirm.time') }}</th>
                @if (!empty($side['withFuel']))<th>{{ __('confirm.fuel_type') }}</th>@endif
                <th>{{ __('confirm.customer_id') }}</th>
                @if ($side['detail'] === 'payment')
                    <th>{{ __('confirm.payment_method') }}</th>
                @else
                    <th>{{ __('confirm.sale_type') }}</th>
                    <th>{{ __('confirm.usage') }}</th>
                @endif
                <th class="text-right">{{ __('confirm.amount_kg') }}</th>
                <th class="text-right">{{ __('confirm.total_amount') }}</th>
                <th class="text-right">{{ __('confirm.paid_amount') }}</th>
                <th class="text-right">{{ __('confirm.unpaid_amount') }}</th>
                <th class="text-right">{{ __('confirm.actions') }}</th>
            @endforeach
        </tr>
        @endif
        </thead>
        <tbody>
        @for ($i = 0; $i < $lines; $i++)
            <tr>
                @foreach ($sides as $side)
                    @php($sale = $side['rows']->get($i))
                    @if ($sale)
                        <td class="{{ !$loop->first ? 'border-left' : '' }}">
                            @if ($sale->confirmed_at)
                                <i class="fas fa-check-circle text-success" title="{{ __('confirm.confirmed') }}"></i><span class="sr-only">{{ __('confirm.confirmed') }}</span>
                            @else
                                <input type="checkbox" name="sales[]" value="{{ $sale->id }}" class="{{ $side['target'] }}">
                            @endif
                        </td>
                        <td>{{ $sale->created_at ? $sale->created_at->format('H:i') : '' }}</td>
                        @if (!empty($side['withFuel']))<td>{{ $fuels[$sale->fuel_type] ?? $sale->fuel_type }}</td>@endif
                        <td>{{ $sale->card_id ? ($cards[$sale->card_id] ?? '') : ($customers[$sale->customer_id] ?? '') }}</td>
                        @if ($side['detail'] === 'payment')
                            <td>{{ __('confirm.payment_' . $sale->payment_method) }}</td>
                            @foreach ($side['specs'] ?? [] as $spec => $kg)
                                <td class="text-right">{{ $sale->ticketsOfSpec($spec) ?: '' }}</td>
                            @endforeach
                        @else
                            <td>{{ __('confirm.sale_type_' . $sale->sale_type) }}</td>
                            <td>{{ __('confirm.usage_' . $sale->usage) }}</td>
                        @endif
                        <td class="text-right">{{ number_format($sale->amount_kg, 2) }}</td>
                        <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
                        <td class="text-right">{{ number_format($sale->paid_amount, 2) }}</td>
                        <td class="text-right">{{ number_format($sale->unpaid_amount, 2) }}</td>
                        <td class="text-right">@include('confirmation._actions', ['kind' => 'sale', 'record' => $sale])</td>
                    @else
                        <td colspan="{{ $columns($side) }}" class="{{ !$loop->first ? 'border-left' : '' }}"></td>
                    @endif
                @endforeach
            </tr>
        @endfor
        @if ($lines === 0)
            <tr><td colspan="{{ array_sum(array_map($columns, $sides)) }}" class="text-muted">{{ __('confirm.nothing_here') }}</td></tr>
        @endif
        </tbody>
        @if ($lines > 0)
            <tfoot>
            <tr class="font-weight-bold">
                @foreach ($sides as $side)
                    <td colspan="{{ $columns($side) - 5 - count($side['specs'] ?? []) }}" class="{{ !$loop->first ? 'border-left' : '' }}">{{ __('confirm.total') }}</td>
                    @foreach ($side['specs'] ?? [] as $spec => $kg)
                        <td class="text-right">{{ $side['rows']->sum(function ($sale) use ($spec) { return $sale->ticketsOfSpec($spec); }) ?: '' }}</td>
                    @endforeach
                    <td class="text-right">{{ number_format($side['rows']->sum('amount_kg'), 2) }}</td>
                    <td class="text-right">{{ number_format($side['rows']->sum('total_amount'), 2) }}</td>
                    <td class="text-right">{{ number_format($side['rows']->sum('paid_amount'), 2) }}</td>
                    <td class="text-right">{{ number_format($side['rows']->sum('unpaid_amount'), 2) }}</td>
                    <td></td>
                @endforeach
            </tr>
            </tfoot>
        @endif
    </table>
</div>
