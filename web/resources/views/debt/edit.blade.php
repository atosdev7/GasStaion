@extends('layouts.app')

@section('title', __('debts.edit_payment'))

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <div class="card">
        <form method="POST" action="{{ route('payments.update', $payment->id) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-group">
                    <label>{{ __('debts.customer_id') }}</label>
                    <input type="text" readonly value="{{ $customer }}" class="form-control-plaintext">
                </div>
                <div class="form-group">
                    <label for="f_debt_id">{{ __('debts.apply_to_debt') }}</label>
                    <select id="f_debt_id" name="debt_id" class="form-control js-select2 @error('debt_id') is-invalid @enderror" data-no-placeholder>
                        <option value="">{{ __('debts.general_payment_option') }}</option>
                        @foreach ($debts as $debt)
                            <option value="{{ $debt->id }}" data-method="{{ $debt->payment_method }}" {{ old('debt_id', $payment->debt_id) === $debt->id ? 'selected' : '' }}>
                                {{ $debt->display_date ? substr((string) $debt->display_date, 0, 10) : __('debts.opening_balance') }} — {{ __('debts.balance') }}: {{ number_format($debt->balance, 2) }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('debts.apply_to_debt_hint') }}</small>
                    @error('debt_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_amount">{{ __('debts.amount') }}</label>
                    <input id="f_amount" name="amount" type="number" step="0.01" min="0" value="{{ old('amount', $payment->amount) }}" class="form-control @error('amount') is-invalid @enderror">
                    @error('amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_payment_method">{{ __('debts.payment_method') }}</label>
                    <select id="f_payment_method" name="payment_method" class="form-control @error('payment_method') is-invalid @enderror">
                        <option value="cash" {{ old('payment_method', $payment->payment_method) === 'cash' ? 'selected' : '' }}>{{ __('debts.payment_cash') }}</option>
                        <option value="bank" {{ old('payment_method', $payment->payment_method) === 'bank' ? 'selected' : '' }}>{{ __('debts.payment_bank') }}</option>
                    </select>
                    @error('payment_method') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_paid_date">{{ __('debts.paid_date') }}</label>
                    <input id="f_paid_date" name="paid_date" type="date" value="{{ old('paid_date', substr((string) $payment->paid_date, 0, 10)) }}" class="form-control @error('paid_date') is-invalid @enderror">
                    @error('paid_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_note">{{ __('debts.note') }}</label>
                    <input id="f_note" name="note" type="text" maxlength="255" value="{{ old('note', $payment->note) }}" class="form-control @error('note') is-invalid @enderror">
                    @error('note') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success">{{ __('debts.save') }}</button>
                <a href="{{ route('payments.index', ['tab' => 'payments']) }}" class="btn btn-default">{{ __('debts.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
// a debt chosen is paid the way it was made (the method can still be changed)
$('#f_debt_id').on('change', function () {
    var method = $(this).find('option:selected').data('method');
    if (method) { $('#f_payment_method').val(method); }
});
</script>
@endpush