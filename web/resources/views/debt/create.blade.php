@extends('layouts.app')

@section('title', __('debts.record_payment'))

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('payments.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label for="f_customer_id">{{ __('debts.customer_id') }}</label>
                    <select id="f_customer_id" name="customer_id" class="form-control js-select2 @error('customer_id') is-invalid @enderror">
                        <option value=""></option>
                        @foreach ($customers as $id => $name)
                            <option value="{{ $id }}" {{ (string) old('customer_id', $preset['customer_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_debt_id">{{ __('debts.apply_to_debt') }}</label>
                    <select id="f_debt_id" name="debt_id" class="form-control js-select2 @error('debt_id') is-invalid @enderror" data-no-placeholder>
                        <option value="">{{ __('debts.general_payment_option') }}</option>
                        @foreach ($openDebts as $debt)
                            <option value="{{ $debt->id }}" data-customer="{{ $debt->customer_id }}" data-method="{{ $debt->payment_method }}"
                                    {{ old('debt_id', $preset['debt_id'] ?? '') === $debt->id ? 'selected' : '' }}>
                                {{ $debt->display_date ? $debt->display_date : __('debts.opening_balance') }} — {{ __('debts.balance') }}: {{ number_format($debt->balance, 2) }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('debts.apply_to_debt_hint') }}</small>
                    @error('debt_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_amount">{{ __('debts.amount') }}</label>
                    <input id="f_amount" name="amount" type="number" step="0.01" min="0" value="{{ old('amount', $preset['amount'] ?? '') }}" class="form-control @error('amount') is-invalid @enderror">
                    @error('amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_payment_method">{{ __('debts.payment_method') }}</label>
                    <select id="f_payment_method" name="payment_method" class="form-control @error('payment_method') is-invalid @enderror">
                        <option value="cash" {{ old('payment_method', $preset['payment_method'] ?? 'cash') === 'cash' ? 'selected' : '' }}>{{ __('debts.payment_cash') }}</option>
                        <option value="bank" {{ old('payment_method', $preset['payment_method'] ?? 'cash') === 'bank' ? 'selected' : '' }}>{{ __('debts.payment_bank') }}</option>
                    </select>
                    @error('payment_method') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_paid_date">{{ __('debts.paid_date') }}</label>
                    <input id="f_paid_date" name="paid_date" type="date" value="{{ old('paid_date', date('Y-m-d')) }}" class="form-control @error('paid_date') is-invalid @enderror">
                    @error('paid_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_note">{{ __('debts.note') }}</label>
                    <input id="f_note" name="note" type="text" value="{{ old('note') }}" class="form-control @error('note') is-invalid @enderror">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success">{{ __('debts.record_payment') }}</button>
                <a href="{{ route('payments.index') }}" class="btn btn-default">{{ __('debts.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var customer = document.getElementById('f_customer_id');
    var debt = document.getElementById('f_debt_id');
    var allOptions = Array.prototype.slice.call(debt.options);

    function filterDebtsForCustomer() {
        var current = customer.value;
        debt.innerHTML = '';
        allOptions.forEach(function (opt) {
            var owner = opt.getAttribute('data-customer');
            if (!owner || owner === current) {
                debt.appendChild(opt);
            }
        });
    }

    // Select2 reports a change through jQuery only, so the customer is listened to with jQuery; the debts are listed again for the new customer
    $(customer).on('change', function () {
        filterDebtsForCustomer();
        $(debt).val('').trigger('change.select2');
    });
    filterDebtsForCustomer();

    // a debt chosen is paid the way it was made (the method can still be changed)
    $(debt).on('change', function () {
        var method = $(debt).find('option:selected').data('method');
        if (method) { $('#f_payment_method').val(method); }
    });
})();
</script>
@endpush
