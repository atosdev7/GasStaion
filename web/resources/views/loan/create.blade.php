@extends('layouts.app')

@section('title', __('debts.record_settlement'))

@section('content')
    @if ($openLoans->isEmpty())
        <div class="alert alert-warning">{{ __('debts.no_open_loans') }}</div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('loans.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label for="f_loan_id">{{ __('debts.apply_to_loan') }}</label>
                    <select id="f_loan_id" name="loan_id" class="form-control js-select2 @error('loan_id') is-invalid @enderror">
                        @foreach ($openLoans as $loan)
                            <option value="{{ $loan->id }}" {{ old('loan_id') === $loan->id ? 'selected' : '' }}>
                                {{ $customers[$loan->customer_id] ?? $loan->customer_id }} —
                                {{ $fuels[$loan->fuel_type] ?? $loan->fuel_type }} —
                                {{ __('debts.balance') }}: {{ number_format($loan->balance_kg, 2) }} kg
                            </option>
                        @endforeach
                    </select>
                    @error('loan_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_ticket_barcode">{{ __('debts.ticket_barcode') }}</label>
                    <input id="f_ticket_barcode" name="ticket_barcode" type="text" maxlength="16" value="{{ old('ticket_barcode') }}" class="form-control @error('ticket_barcode') is-invalid @enderror" autofocus>
                    <small class="form-text text-muted">{{ __('debts.ticket_barcode_hint') }}</small>
                    @error('ticket_barcode') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success">{{ __('debts.record_settlement') }}</button>
                <a href="{{ route('loans.index') }}" class="btn btn-default">{{ __('debts.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
