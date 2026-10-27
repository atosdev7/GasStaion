@extends('layouts.app')

@section('title', __('menu.group_init') . ' - ' . __('menu.ticket_loans'))

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if ($locked)
        <p class="text-muted">{{ __('initialize.loan_locked_hint') }}</p>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.loan_edit') }}</h3></div>
        <form method="POST" action="{{ route('balances.loans.update', $loan->id) }}" id="opening-loan-edit">
            @csrf @method('PUT')
            <div class="card-body">
                <div class="form-row">
                    <div class="col-md-3 form-group">
                        <label for="f_loan_customer">{{ __('initialize.customer_id') }}</label>
                        <select id="f_loan_customer" name="customer_id" class="form-control js-select2" {{ $locked ? 'disabled' : '' }}>
                            @foreach ($customers as $id => $name)
                                <option value="{{ $id }}" {{ (string) old('customer_id', $loan->customer_id) === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_loan_fuel">{{ __('initialize.fuel_type') }}</label>
                        <select id="f_loan_fuel" name="fuel_type" class="form-control" {{ $locked ? 'disabled' : '' }}>
                            @foreach ($fuels as $key => $label)
                                <option value="{{ $key }}" {{ old('fuel_type', $loan->fuel_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_loan_kg">{{ __('initialize.amount_kg') }}</label>
                        <input id="f_loan_kg" name="amount_kg" type="number" step="0.01" min="0.01" value="{{ old('amount_kg', $loan->amount_kg) }}" class="form-control" {{ $locked ? 'readonly' : 'required' }}>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_loan_date">{{ __('initialize.as_of') }}</label>
                        <input id="f_loan_date" name="date" type="date" max="{{ today()->toDateString() }}" class="form-control" required value="{{ old('date', substr((string) $loan->date, 0, 10)) }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="f_loan_comment">{{ __('initialize.comment') }}</label>
                        <input id="f_loan_comment" name="comment" type="text" maxlength="255" value="{{ old('comment', $loan->comment) }}" class="form-control">
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('initialize.save') }}</button>
                <a href="{{ route('balances.loans.index') }}" class="btn btn-default">{{ __('initialize.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
