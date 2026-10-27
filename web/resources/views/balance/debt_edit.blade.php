@extends('layouts.app')

@section('title', __('menu.group_init') . ' - ' . __('menu.init_debts'))

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if ($locked)
        <p class="text-muted">{{ __('initialize.debt_locked_hint') }}</p>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.debt_edit') }}</h3></div>
        <form method="POST" action="{{ route('balances.debts.update', $debt->id) }}" id="opening-debt-edit">
            @csrf @method('PUT')
            <div class="card-body">
                <div class="form-row">
                    <div class="col-md-3 form-group">
                        <label for="f_debt_customer">{{ __('initialize.customer_id') }}</label>
                        <select id="f_debt_customer" name="customer_id" class="form-control js-select2" {{ $locked ? 'disabled' : '' }}>
                            @foreach ($customers as $id => $name)
                                <option value="{{ $id }}" {{ (string) old('customer_id', $debt->customer_id) === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_debt_amount">{{ __('initialize.amount') }}</label>
                        <input id="f_debt_amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', $debt->amount) }}" class="form-control" {{ $locked ? 'readonly' : 'required' }}>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_debt_method">{{ __('initialize.payment_method') }}</label>
                        <select id="f_debt_method" name="payment_method" class="form-control" {{ $locked ? 'disabled' : '' }}>
                            @foreach (['cash', 'bank'] as $method)
                                <option value="{{ $method }}" {{ old('payment_method', $debt->payment_method) === $method ? 'selected' : '' }}>{{ __('initialize.payment_' . $method) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_debt_date">{{ __('initialize.as_of') }}</label>
                        <input id="f_debt_date" name="debt_date" type="date" max="{{ today()->toDateString() }}" class="form-control" required
                               value="{{ old('debt_date', \App\Support\Debts\CustomerBalances::debtDay($debt)) }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="f_debt_comment">{{ __('initialize.comment') }}</label>
                        <input id="f_debt_comment" name="comment" type="text" maxlength="255" value="{{ old('comment', $debt->comment) }}" class="form-control">
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('initialize.save') }}</button>
                <a href="{{ route('balances.debts.index') }}" class="btn btn-default">{{ __('initialize.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
