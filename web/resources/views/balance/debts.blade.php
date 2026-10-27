@extends('layouts.app')

@section('title', __('menu.group_init') . ' - ' . __('menu.init_debts'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <p class="text-muted">{{ __('initialize.opening_customer_hint') }}</p>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.opening_debts') }}</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('balances.debts.store') }}" class="form-row align-items-end">
                @csrf
                <div class="col-md-3 form-group">
                    <label for="f_debt_customer">{{ __('initialize.customer_id') }}</label>
                    <select id="f_debt_customer" name="customer_id" class="form-control js-select2">
                        @foreach ($customers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label for="f_debt_amount">{{ __('initialize.amount') }}</label>
                    <input id="f_debt_amount" name="amount" type="number" step="0.01" min="0.01" class="form-control">
                </div>
                <div class="col-md-2 form-group">
                    <label for="f_debt_method">{{ __('initialize.payment_method') }}</label>
                    <select id="f_debt_method" name="payment_method" class="form-control">
                        <option value="cash">{{ __('initialize.payment_cash') }}</option>
                        <option value="bank">{{ __('initialize.payment_bank') }}</option>
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label for="f_debt_date">{{ __('initialize.as_of') }}</label>
                    <input id="f_debt_date" name="debt_date" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" class="form-control">
                </div>
                <div class="col-md-2 form-group">
                    <label for="f_debt_comment">{{ __('initialize.comment') }}</label>
                    <input id="f_debt_comment" name="comment" type="text" maxlength="255" class="form-control">
                </div>
                <div class="col-md-1 form-group">
                    <button type="submit" class="btn btn-success btn-block">{{ __('initialize.new') }}</button>
                </div>
            </form>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('initialize.customer_id') }}</th>
                    <th>{{ __('initialize.payment_method') }}</th>
                    <th>{{ __('initialize.as_of') }}</th>
                    <th class="text-right">{{ __('initialize.amount') }}</th>
                    <th>{{ __('initialize.comment') }}</th>
                    <th class="text-right">{{ __('initialize.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($debts as $debt)
                    <tr>
                        <td>{{ $customers[$debt->customer_id] ?? $debt->customer_id }}</td>
                        <td>{{ __('initialize.payment_' . $debt->payment_method) }}</td>
                        <td>{{ \App\Support\Debts\CustomerBalances::debtDay($debt) }}</td>
                        <td class="text-right">{{ number_format($debt->amount, 2) }}</td>
                        <td>{{ $debt->comment }}</td>
                        <td class="text-right text-nowrap">
                            <a href="{{ route('balances.debts.edit', $debt->id) }}" class="btn btn-sm btn-outline-primary">{{ __('initialize.edit') }}</a>
                            @unless ($paidDebtIds->has($debt->id))
                                <form method="POST" action="{{ route('balances.debts.destroy', $debt->id) }}" class="d-inline"
                                      onsubmit="return confirm(@json(__('initialize.opening_delete_confirm')))">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('initialize.delete') }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
