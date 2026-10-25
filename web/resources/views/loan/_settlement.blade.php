{{-- One settlement: its fields, the loan it pays and the settlement it reverses or that reversed it. Shown in the popup of the settlements list and, with a layout round it, as a page. --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            {{ $customer }} &middot; {{ substr((string) ($settlement->date ?: $settlement->settled_at), 0, 10) }}
            @if ($settlement->reverses_settlement_id)
                <span class="badge badge-secondary ml-1">{{ __('debts.settlement_reversal') }}</span>
            @elseif ($reversedBy)
                <span class="badge badge-secondary ml-1">{{ __('debts.reversed') }}</span>
            @endif
            @if ($settlement->isConfirmed())
                <span class="badge badge-success ml-1"><i class="fas fa-lock"></i> {{ __('debts.settlement_confirmed') }}</span>
            @endif
        </h3>
    </div>
    <div class="card-body">
        <table class="table table-sm mb-0">
            <tr><th style="width: 220px">{{ __('debts.customer_id') }}</th><td>{{ $customer }}</td></tr>
            <tr><th>{{ __('debts.fuel_type') }}</th><td>{{ $settlement->loan ? ($fuels[$settlement->loan->fuel_type] ?? $settlement->loan->fuel_type) : '' }}</td></tr>
            <tr><th>{{ __('debts.settled_on') }}</th><td>{{ substr((string) ($settlement->date ?: $settlement->settled_at), 0, 10) }}</td></tr>
            <tr><th>{{ __('debts.settled_at_time') }}</th><td>{{ $settlement->settled_at }}</td></tr>
            <tr><th>{{ __('debts.ticket_barcode') }}</th><td>{{ $settlement->ticket_barcode }}</td></tr>
            <tr><th>{{ __('debts.amount_kg') }}</th><td class="{{ $settlement->amount_kg < 0 ? 'text-danger' : '' }}">{{ number_format($settlement->amount_kg, 2) }}</td></tr>
            <tr>
                <th>{{ __('debts.settlement_of') }}</th>
                <td>
                    @if ($settlement->loan)
                        <a href="{{ route('loans.show', $settlement->loan_id) }}" class="loan-detail">{{ substr((string) ($settlement->loan->date ?: $settlement->loan->loaned_at), 0, 10) }} &middot; {{ number_format($settlement->loan->amount_kg, 2) }} kg</a>
                    @endif
                </td>
            </tr>
            <tr><th>{{ __('debts.entered_by') }}</th><td>{{ $users[$settlement->user_id] ?? '' }}</td></tr>
            <tr><th>{{ __('debts.note') }}</th><td>{{ $settlement->comment }}</td></tr>
            @if ($settlement->reverses_settlement_id)
                <tr><th>{{ __('debts.settlement_reversal') }}</th><td><a href="{{ route('loans.settlements.show', $settlement->reverses_settlement_id) }}" class="settlement-detail">{{ __('debts.view') }}</a></td></tr>
            @elseif ($reversedBy)
                <tr><th>{{ __('debts.reversed') }}</th><td><a href="{{ route('loans.settlements.show', $reversedBy) }}" class="settlement-detail">{{ __('debts.view') }}</a></td></tr>
            @endif
        </table>
    </div>
</div>
