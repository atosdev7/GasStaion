{{-- One debt: its fields, the sale it came from, the returns that cut it and the payments that settle it. Shown in the popup of the debts list and, with a layout round it, as a page. --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            {{ $customer }} &middot; {{ $debt->display_date ? substr((string) $debt->display_date, 0, 10) : __('debts.opening_balance') }}
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
            <tr><th>{{ __('debts.payment_method') }}</th><td>{{ __('debts.payment_' . $debt->payment_method) }}</td></tr>
            <tr>
                <th>{{ __('debts.sale_made') }}</th>
                <td>
                    @if ($debt->sale)
                        @if ($saleLink)
                            <a href="{{ route('ticket-sales.show', $debt->sale_id) }}">{{ __('debts.debt_view_sale') }}: {{ substr((string) $debt->sale->sale_date, 0, 10) }}</a>
                        @else
                            {{ substr((string) $debt->sale->sale_date, 0, 10) }}
                        @endif
                    @else
                        {{ __('debts.opening_balance') }}
                    @endif
                </td>
            </tr>
            <tr><th>{{ __('debts.amount') }}</th><td>{{ number_format($debt->amount, 2) }}</td></tr>
            @foreach ($debt->adjustments as $adjustment)
                <tr><th>{{ __('debts.returns_cut') }}</th><td>{{ number_format($adjustment->amount, 2) }} ({{ substr((string) $adjustment->adjusted_date, 0, 10) }})</td></tr>
            @endforeach
            <tr><th>{{ __('debts.net_owed') }}</th><td>{{ number_format($debt->net_amount, 2) }}</td></tr>
            <tr><th>{{ __('debts.settled_amount') }}</th><td>{{ number_format($settled, 2) }}</td></tr>
            <tr class="font-weight-bold"><th>{{ __('debts.balance') }}</th><td>{{ number_format($balance, 2) }}</td></tr>
        </table>

        <h6 class="font-weight-bold mt-3">{{ __('debts.settled_by') }} ({{ $payments->count() }})</h6>
        @if ($payments->isEmpty())
            <div class="text-muted">{{ __('debts.no_payments_yet') }}</div>
        @else
            <table class="table table-sm">
                <thead>
                <tr>
                    <th>{{ __('debts.payment_date') }}</th>
                    <th>{{ __('debts.payment_method') }}</th>
                    <th class="text-right">{{ __('debts.amount') }}</th>
                    <th>{{ __('debts.entered_by') }}</th>
                    <th>{{ __('debts.note') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ substr((string) $payment->paid_date, 0, 10) }}</td>
                        <td>{{ __('debts.payment_' . $payment->payment_method) }}</td>
                        <td class="text-right {{ $payment->amount < 0 ? 'text-danger' : '' }}">{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ $users[$payment->user_id] ?? '' }}</td>
                        <td>{{ $payment->note }}</td>
                        <td>
                            @if ($payment->reverses_payment_id)
                                <a href="{{ route('payments.show', $payment->reverses_payment_id) }}" class="badge badge-secondary payment-detail">{{ __('debts.reversal_of', ['id' => $payment->reverses_payment_id]) }}</a>
                            @elseif (isset($reversed[$payment->id]))
                                <a href="{{ route('payments.show', $reversed[$payment->id]) }}" class="badge badge-secondary payment-detail">{{ __('debts.reversed') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>