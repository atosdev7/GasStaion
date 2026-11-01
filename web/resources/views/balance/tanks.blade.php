@extends('layouts.app')

@section('title', __('menu.group_init') . ' - ' . __('menu.tanks'))

@section('content')
    @include('balance._lock')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <p class="text-muted">{{ __('initialize.opening_tank_hint') }}</p>

    {{-- add the fuel a tank holds at one cost, like a fuel inbound --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.tank_balance_add') }}</h3></div>
        <form method="POST" action="{{ route('balances.tanks.store') }}" class="form-row align-items-end p-3" id="tank-balance-form">
            @csrf
            <div class="col-md-2 form-group">
                <label for="f_tb_tank">{{ __('initialize.tank_id') }}</label>
                <select id="f_tb_tank" name="tank_id" class="form-control" required>
                    @foreach ($tanks as $id => $name)
                        <option value="{{ $id }}" {{ (string) old('tank_id') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 form-group">
                <label for="f_tb_cost">{{ __('initialize.cost') }}</label>
                <input id="f_tb_cost" name="cost" type="number" step="0.0001" min="0" value="{{ old('cost') }}" class="form-control" required>
            </div>
            <div class="col-md-2 form-group">
                <label for="f_tb_balance">{{ __('initialize.balance') }}</label>
                <input id="f_tb_balance" name="balance" type="number" step="0.01" min="0" value="{{ old('balance') }}" class="form-control" required>
            </div>
            <div class="col-md-2 form-group">
                <label for="f_tb_date">{{ __('initialize.balance_date') }}</label>
                <input id="f_tb_date" name="balance_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('balance_date', today()->toDateString()) }}" class="form-control" required>
            </div>
            <div class="col-md-3 form-group">
                <label for="f_tb_note">{{ __('initialize.comment') }}</label>
                <input id="f_tb_note" name="note" type="text" maxlength="255" value="{{ old('note') }}" class="form-control">
            </div>
            <div class="col-md-1 form-group">
                <button type="submit" class="btn btn-primary btn-block">{{ __('initialize.tank_balance_add_button') }}</button>
            </div>
            <div class="col-12 form-group mb-0">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="f_tb_default" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}>
                    <label class="custom-control-label" for="f_tb_default">{{ __('initialize.tank_balance_default_box') }}</label>
                </div>
                <small class="form-text text-muted">{{ __('fuel.default_hint') }}</small>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.tank_balances') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap" id="tank-balances">
                <thead>
                <tr>
                    <th>{{ __('initialize.tank_id') }}</th>
                    <th>{{ __('initialize.fuel_type') }}</th>
                    <th class="text-right">{{ __('initialize.cost') }}</th>
                    <th></th>
                    <th class="text-right">{{ __('initialize.balance') }}</th>
                    <th>{{ __('initialize.balance_date') }}</th>
                    <th>{{ __('initialize.comment') }}</th>
                    <th>{{ __('initialize.tank_balance_source') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($balances as $row)
                    {{-- the default cost is a value of its own: a row whose cost is that value is the default one --}}
                    @php($isDefault = $row->cost !== null && ($defaults[$row->fuel_type] ?? null) !== null && abs($defaults[$row->fuel_type] - round((float) $row->cost, 4)) < 0.00005)
                    <tr class="tank-balance-{{ $row->id }}">
                        <td>{{ $tanks[$row->tank_id] ?? $row->tank_id }}</td>
                        <td>{{ $fuels[$row->fuel_type] ?? $row->fuel_type }}</td>
                        <td class="text-right">{{ $row->cost !== null ? number_format($row->cost, 4) : '-' }}</td>
                        <td>@if ($isDefault)<span class="badge badge-success">{{ __('fuel.default') }}</span>@endif</td>
                        <td class="text-right">{{ number_format($row->balance, 2) }}</td>
                        <td>{{ $row->balance_date ? substr((string) $row->balance_date, 0, 10) : '' }}</td>
                        <td>{{ $row->note }}</td>
                        <td>{{ __('initialize.tank_balance_' . ($row->isManual() ? 'manual' : 'backup')) }}</td>
                        <td class="text-right">
                            @if (!$isDefault && $row->cost !== null)
                                <form method="POST" action="{{ route('balances.tanks.default', $row->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-outline-secondary">{{ __('fuel.make_default') }}</button>
                                </form>
                            @endif
                            <a href="{{ route('balances.tanks.edit', $row->id) }}" class="btn btn-xs btn-outline-primary">{{ __('initialize.edit') }}</a>
                            @if ($row->isManual())
                                <form method="POST" action="{{ route('balances.tanks.destroy', $row->id) }}" class="d-inline" onsubmit="return confirm(@json(__('initialize.opening_delete_confirm')))">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger">{{ __('initialize.delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">{{ __('initialize.no_tank_balances') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- the summary of each tank: what it started with, what came in and went out since, what it holds now --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.tank_summary') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap" id="tank-summary">
                <thead>
                <tr>
                    <th>{{ __('initialize.tank_id') }}</th>
                    <th>{{ __('initialize.fuel_type') }}</th>
                    <th class="text-right">{{ __('initialize.balance') }}</th>
                    <th class="text-right">{{ __('initialize.summary_inbound') }}</th>
                    <th class="text-right">{{ __('initialize.summary_outbound') }}</th>
                    <th class="text-right">{{ __('initialize.summary_current') }}</th>
                    <th class="text-right">{{ __('initialize.summary_capacity') }}</th>
                    <th class="text-right">{{ __('initialize.summary_percent') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($levels as $level)
                    <tr class="summary-tank-{{ $level->tank->id }}">
                        <td>{{ $level->tank->name }}</td>
                        <td>{{ $fuels[$level->tank->fuel_type] ?? $level->tank->fuel_type }}</td>
                        <td class="text-right">{{ number_format($level->opening, 2) }}</td>
                        <td class="text-right">{{ number_format($level->inbound, 2) }}</td>
                        <td class="text-right">{{ number_format($level->supplied, 2) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($level->current, 2) }}</td>
                        <td class="text-right">{{ $level->capacity !== null ? number_format($level->capacity, 2) : '-' }}</td>
                        <td class="text-right">{{ $level->percent !== null ? number_format($level->percent, 1) . '%' : '-' }}</td>
                    </tr>
                @endforeach
                @if ($levels->isEmpty())
                    <tr><td colspan="8" class="text-muted">{{ __('initialize.no_tanks') }}</td></tr>
                @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection
