@extends('layouts.app')

@section('title', __('menu.group_init') . ' - ' . __('menu.ticket_loans'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <p class="text-muted">{{ __('initialize.opening_customer_hint') }}</p>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.opening_loans') }}</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('balances.loans.store') }}" class="form-row align-items-end">
                @csrf
                <div class="col-md-4 form-group">
                    <label for="f_loan_customer">{{ __('initialize.customer_id') }}</label>
                    <select id="f_loan_customer" name="customer_id" class="form-control js-select2">
                        @foreach ($customers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="f_loan_fuel">{{ __('initialize.fuel_type') }}</label>
                    <select id="f_loan_fuel" name="fuel_type" class="form-control">
                        @foreach ($fuels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="f_loan_kg">{{ __('initialize.amount_kg') }}</label>
                    <input id="f_loan_kg" name="amount_kg" type="number" step="0.01" min="0.01" class="form-control">
                </div>
                <div class="col-md-2 form-group">
                    <button type="submit" class="btn btn-success btn-block">{{ __('initialize.new') }}</button>
                </div>
            </form>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('initialize.customer_id') }}</th>
                    <th>{{ __('initialize.fuel_type') }}</th>
                    <th class="text-right">{{ __('initialize.amount_kg') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($loans as $loan)
                    <tr>
                        <td>{{ $customers[$loan->customer_id] ?? $loan->customer_id }}</td>
                        <td>{{ $fuels[$loan->fuel_type] ?? $loan->fuel_type }}</td>
                        <td class="text-right">{{ number_format($loan->amount_kg, 2) }}</td>
                        <td class="text-right">
                            @if ($loan->settled_amount_kg == 0)
                                <form method="POST" action="{{ route('balances.loans.destroy', $loan->id) }}" class="d-inline"
                                      onsubmit="return confirm(@json(__('initialize.opening_delete_confirm')))">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('initialize.delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
