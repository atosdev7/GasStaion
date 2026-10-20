{{-- One sale: its fields and the items it was entered with (spec, count, start and end serial). Shown in the popup of the sales list and, with a layout round it, as a page. --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            {{ __('sales.sale_date') }}: {{ $sale->sale_date }}
            @if ($sale->deleted_at)
                <span class="badge badge-danger ml-1">{{ __('sale_delete.deleted_at') }}: {{ $sale->deleted_at }}</span>
            @endif
            @if ($sale->isReturn())
                <span class="badge badge-warning ml-1">{{ __('sale_return.badge') }}</span>
            @endif
        </h3>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <tr><th style="width: 200px">{{ __('sales.customer_id') }}</th><td>{{ $customer }}</td></tr>
            <tr><th>{{ __('sales.sale_type') }}</th><td>{{ __('sales.sale_type_' . $sale->sale_type) }}</td></tr>
            <tr><th>{{ __('sales.fuel_type') }}</th><td>{{ $fuels[$sale->fuel_type] ?? $sale->fuel_type }}</td></tr>
            <tr><th>{{ __('sales.amount_kg') }}</th><td>{{ number_format($sale->amount_kg, 2) }} {{ __('sales.kg') }}</td></tr>
            <tr><th>{{ __('sales.price') }}</th><td>{{ number_format($sale->price, 2) }}</td></tr>
            <tr><th>{{ __('sales.usage') }}</th><td>{{ __('sales.usage_' . $sale->usage) }}</td></tr>
            <tr><th>{{ __('sales.payment_method') }}</th><td>{{ __('sales.payment_' . $sale->payment_method) }}</td></tr>
            <tr><th>{{ __('sales.total_amount') }}</th><td>{{ number_format($sale->total_amount, 2) }}</td></tr>
            <tr><th>{{ __('sales.paid_amount') }}</th><td>{{ number_format($sale->paid_amount, 2) }}</td></tr>
            <tr><th>{{ __('sales.unpaid_amount') }}</th><td>{{ number_format($sale->unpaid_amount, 2) }}</td></tr>
            <tr><th>{{ __('sales.receipt') }}</th><td>{{ $sale->receipt ? __('sales.receipt_issued') : '-' }}</td></tr>
            <tr><th>{{ __('sales.comment') }}</th><td>{{ $sale->comment }}</td></tr>
            <tr><th>{{ __('sales.sale_made') }}</th><td>{{ $sale->created_at }}@if ($user) &middot; {{ $user }}@endif</td></tr>
            @if ($sale->confirmed_at)
                <tr><th>{{ __('sales.sale_confirmed') }}</th><td>{{ $sale->confirmed_at }}</td></tr>
            @endif
        </table>

        @if ($items->isNotEmpty())
            <h6 class="font-weight-bold mt-3">{{ __('sales.sale_items') }}</h6>
            <table class="table table-sm table-bordered w-auto">
                <thead>
                <tr>
                    <th>{{ __('sales.spec') }}</th>
                    <th class="text-right">{{ __('sales.quantity') }}</th>
                    <th class="text-right">{{ __('sales.start_serial') }}</th>
                    <th class="text-right">{{ __('sales.end_serial') }}</th>
                    <th class="text-right">{{ __('sales.amount_kg') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ config('ticket_specs.' . $item->spec) }} {{ __('sales.kg') }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">{{ App\Support\Tickets\Barcode::make($sale->fuel_type, $item->spec, $item->start_serial) }}</td>
                        <td class="text-right">{{ App\Support\Tickets\Barcode::make($sale->fuel_type, $item->spec, $item->end_serial) }}</td>
                        <td class="text-right">{{ number_format($item->amount_kg, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
