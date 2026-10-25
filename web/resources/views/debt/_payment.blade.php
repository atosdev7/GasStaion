{{-- One payment: its fields, the debt it pays and the payment it reverses or that reversed it. Shown in the popup of the payments list and, with a layout round it, as a page. --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            {{ $customer }} &middot; {{ substr((string) $payment->paid_date, 0, 10) }}
            @if ($payment->reverses_payment_id)
                <span class="badge badge-secondary ml-1">{{ __('debts.reversal_of', ['id' => $payment->reverses_payment_id]) }}</span>
            @elseif ($reversedBy)
                <span class="badge badge-secondary ml-1">{{ __('debts.reversed') }}</span>
            @endif
            @if ($payment->isConfirmed())
                <span class="badge badge-success ml-1"><i class="fas fa-lock"></i> {{ __('debts.payment_confirmed') }}</span>
            @endif
        </h3>
    </div>
    <div class="card-body">
        <table class="table table-sm mb-0">
            <tr><th style="width: 220px">{{ __('debts.customer_id') }}</th><td>{{ $customer }}</td></tr>
            <tr><th>{{ __('debts.payment_date') }}</th><td>{{ substr((string) $payment->paid_date, 0, 10) }}</td></tr>
            <tr><th>{{ __('debts.payment_method') }}</th><td>{{ __('debts.payment_' . $payment->payment_method) }}</td></tr>
            <tr><th>{{ __('debts.amount') }}</th><td class="{{ $payment->amount < 0 ? 'text-danger' : '' }}">{{ number_format($payment->amount, 2) }}</td></tr>
            <tr>
                <th>{{ __('debts.paid_for') }}</th>
                <td>
                    @if ($payment->debt)
                        <a href="{{ route('debts.show', $payment->debt_id) }}" class="debt-detail">{{ $payment->debt->display_date ? substr((string) $payment->debt->display_date, 0, 10) : __('debts.opening_balance') }}</a>
                    @else
                        <span class="text-muted">{{ __('debts.general_payment') }}</span>
                    @endif
                </td>
            </tr>
            <tr><th>{{ __('debts.entered_by') }}</th><td>{{ $users[$payment->user_id] ?? '' }}</td></tr>
            <tr><th>{{ __('debts.note') }}</th><td>{{ $payment->note }}</td></tr>
            @if ($payment->reverses_payment_id)
                <tr><th>{{ __('debts.reversal_of', ['id' => $payment->reverses_payment_id]) }}</th><td><a href="{{ route('payments.show', $payment->reverses_payment_id) }}" class="payment-detail">#{{ $payment->reverses_payment_id }}</a></td></tr>
            @elseif ($reversedBy)
                <tr><th>{{ __('debts.reversed') }}</th><td><a href="{{ route('payments.show', $reversedBy) }}" class="payment-detail">#{{ $reversedBy }}</a></td></tr>
            @endif
        </table>
    </div>
</div>
