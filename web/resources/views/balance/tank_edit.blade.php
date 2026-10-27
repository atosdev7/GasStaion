@extends('layouts.app')

@section('title', __('menu.group_init') . ' - ' . __('menu.tanks'))

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @unless ($row->isManual())
        <p class="text-muted">{{ __('initialize.tank_balance_backup_hint') }}</p>
    @endunless

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.tank_balance_edit') }}</h3></div>
        <form method="POST" action="{{ route('balances.tanks.update', $row->id) }}" id="tank-balance-edit">
            @csrf @method('PUT')
            <div class="card-body">
                <div class="form-row">
                    <div class="col-md-3 form-group">
                        <label for="f_tb_tank">{{ __('initialize.tank_id') }}</label>
                        <select id="f_tb_tank" name="tank_id" class="form-control" {{ $row->isManual() ? '' : 'disabled' }}>
                            @foreach ($tanks as $id => $name)
                                <option value="{{ $id }}" {{ (string) old('tank_id', $row->tank_id) === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_tb_cost">{{ __('initialize.cost') }}</label>
                        <input id="f_tb_cost" name="cost" type="number" step="0.0001" min="0" class="form-control" required
                               value="{{ old('cost', $row->cost !== null ? rtrim(rtrim(number_format($row->cost, 4, '.', ''), '0'), '.') : '') }}">
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_tb_balance">{{ __('initialize.balance') }}</label>
                        <input id="f_tb_balance" name="balance" type="number" step="0.01" min="0" class="form-control" value="{{ old('balance', $row->balance) }}"
                               {{ $row->isManual() ? 'required' : 'readonly' }}>
                    </div>
                    <div class="col-md-2 form-group">
                        <label for="f_tb_date">{{ __('initialize.balance_date') }}</label>
                        <input id="f_tb_date" name="balance_date" type="date" max="{{ today()->toDateString() }}" class="form-control"
                               value="{{ old('balance_date', $row->balance_date ? substr((string) $row->balance_date, 0, 10) : '') }}" {{ $row->isManual() ? 'required' : 'readonly' }}>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="f_tb_note">{{ __('initialize.comment') }}</label>
                        <input id="f_tb_note" name="note" type="text" maxlength="255" class="form-control" value="{{ old('note', $row->note) }}">
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('initialize.save') }}</button>
                <a href="{{ route('balances.tanks.index') }}" class="btn btn-default">{{ __('initialize.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
