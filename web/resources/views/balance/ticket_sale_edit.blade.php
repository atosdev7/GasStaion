@extends('layouts.app')

@section('title', __('menu.group_init') . ' - ' . __('menu.init_ticket_sales'))

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.ticket_sales_edit') }}</h3></div>
        <form method="POST" action="{{ route('balances.ticket-sales.update', $row->id) }}" id="opening-tickets-edit">
            @csrf @method('PUT')
            <div class="card-body">
                <div class="form-row">
                    <div class="col-md-2 form-group">
                        <label for="f_ts_fuel">{{ __('initialize.fuel_type') }}</label>
                        <select id="f_ts_fuel" name="fuel_type" class="form-control">
                            @foreach ($fuels as $key => $label)
                                <option value="{{ $key }}" {{ old('fuel_type', $row->fuel_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_ts_spec">{{ __('initialize.ticket_spec') }}</label>
                        <select id="f_ts_spec" name="spec" class="form-control">
                            @foreach ($specs as $key => $kg)
                                <option value="{{ $key }}" {{ (string) old('spec', $row->spec) === (string) $key ? 'selected' : '' }}>{{ $kg }} {{ __('initialize.kg') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_ts_qty">{{ __('initialize.ticket_quantity') }}</label>
                        <input id="f_ts_qty" name="quantity" type="number" step="1" min="1" value="{{ old('quantity', $row->quantity) }}" class="form-control" required>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_ts_date">{{ __('initialize.as_of') }}</label>
                        <input id="f_ts_date" name="as_of" type="date" max="{{ today()->toDateString() }}" value="{{ old('as_of', substr((string) $row->as_of, 0, 10)) }}" class="form-control" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="f_ts_comment">{{ __('initialize.comment') }}</label>
                        <input id="f_ts_comment" name="comment" type="text" maxlength="255" value="{{ old('comment', $row->comment) }}" class="form-control">
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('initialize.save') }}</button>
                <a href="{{ route('balances.ticket-sales.index') }}" class="btn btn-default">{{ __('initialize.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
