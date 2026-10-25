{{-- One ticket loan: its fields, the sale it came from and the tickets surrendered against it. Shown in the popup of the loans list and, with a layout round it, as a page. --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            {{ $customer }} &middot; {{ substr((string) ($loan->date ?: $loan->loaned_at), 0, 10) }}
            @if ($balance < -0.005)
                <span class="badge badge-info ml-1">{{ __('debts.debt_credit') }}</span>
            @elseif ($balance <= 0)
                <span class="badge badge-success ml-1">{{ __('debts.debt_settled') }}</span>
            @else
                <span class="badge badge-warning ml-1">{{ __('debts.debt_open') }}</span>
            @endif
        </h3>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <tr><th style="width: 220px">{{ __('debts.customer_id') }}</th><td>{{ $customer }}</td></tr>
            <tr><th>{{ __('debts.fuel_type') }}</th><td>{{ $fuels[$loan->fuel_type] ?? $loan->fuel_type }}</td></tr>
            <tr><th>{{ __('debts.loan_made') }}</th><td>{{ $loan->loaned_at }}</td></tr>
            <tr>
                <th>{{ __('debts.sale_made') }}</th>
                <td>
                    @if ($loan->sale)
                        @if ($saleLink)
                            <a href="{{ route('ticket-sales.show', $loan->sale_id) }}">{{ __('debts.debt_view_sale') }}: {{ substr((string) $loan->sale->sale_date, 0, 10) }}</a>
                        @else
                            {{ substr((string) $loan->sale->sale_date, 0, 10) }}
                        @endif
                    @else
                        {{ __('debts.opening_balance') }}
                    @endif
                </td>
            </tr>
            <tr><th>{{ __('debts.amount_kg') }}</th><td>{{ number_format($loan->amount_kg, 2) }}</td></tr>
            <tr><th>{{ __('debts.settled_amount') }}</th><td>{{ number_format($settled, 2) }}</td></tr>
            <tr class="font-weight-bold"><th>{{ __('debts.balance') }}</th><td>{{ number_format($balance, 2) }}</td></tr>
        </table>

        <h6 class="font-weight-bold mt-3">{{ __('debts.surrendered_tickets') }} ({{ $settlements->count() }})</h6>
        @if ($settlements->isEmpty())
            <div class="text-muted">{{ __('debts.no_settlements_yet') }}</div>
        @else
            <table class="table table-sm">
                <thead>
                <tr>
                    <th>{{ __('debts.settled_on') }}</th>
                    <th>{{ __('debts.ticket_barcode') }}</th>
                    <th class="text-right">{{ __('debts.amount_kg') }}</th>
                    <th>{{ __('debts.entered_by') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($settlements as $settlement)
                    <tr>
                        <td>{{ substr((string) ($settlement->date ?: $settlement->settled_at), 0, 10) }}</td>
                        <td><a href="{{ route('loans.settlements.show', $settlement->id) }}" class="settlement-detail">{{ $settlement->ticket_barcode }}</a></td>
                        <td class="text-right {{ $settlement->amount_kg < 0 ? 'text-danger' : '' }}">{{ number_format($settlement->amount_kg, 2) }}</td>
                        <td>{{ $users[$settlement->user_id] ?? '' }}</td>
                        <td>
                            @if ($settlement->reverses_settlement_id)
                                <a href="{{ route('loans.settlements.show', $settlement->reverses_settlement_id) }}" class="badge badge-secondary settlement-detail">{{ __('debts.settlement_reversal') }}</a>
                            @elseif (isset($reversed[$settlement->id]))
                                <a href="{{ route('loans.settlements.show', $reversed[$settlement->id]) }}" class="badge badge-secondary settlement-detail">{{ __('debts.reversed') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
