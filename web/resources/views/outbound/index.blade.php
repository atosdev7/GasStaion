@extends('layouts.app')

@section('title', __('menu.outbounds'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('outbound.index') }}" class="form-inline">
                <label for="f_from" class="mr-2">{{ __('fuel.from') }}</label>
                <input id="f_from" name="from" type="date" value="{{ $from }}" class="form-control mr-3">
                <label for="f_to" class="mr-2">{{ __('fuel.to') }}</label>
                <input id="f_to" name="to" type="date" value="{{ $to }}" class="form-control mr-3">
                <label for="f_tank_id" class="mr-2">{{ __('fuel.tank_id') }}</label>
                <select id="f_tank_id" name="tank_id" class="form-control mr-3">
                    <option value="">{{ __('fuel.all_tanks') }}</option>
                    @foreach ($tanks as $id => $name)
                        <option value="{{ $id }}" {{ (string) $tankId === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">{{ __('fuel.show') }}</button>
            </form>
            @if ($errors->any())
                <div class="text-danger mt-2">{{ $errors->first() }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('fuel.outbound_per_tank') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap" id="outbound-per-tank">
                <thead>
                <tr>
                    <th>{{ __('fuel.tank_id') }}</th>
                    <th class="text-right">{{ __('fuel.count') }}</th>
                    <th class="text-right">{{ __('fuel.liters') }}</th>
                    <th class="text-right">{{ __('fuel.amount_kg') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($perTank as $t)
                    <tr>
                        <td>{{ $t->tank_id ? ($tankNames[$t->tank_id] ?? $t->tank_id) : '-' }}</td>
                        <td class="text-right">{{ $t->supplies }}</td>
                        <td class="text-right">{{ number_format($t->liters, 2) }}</td>
                        <td class="text-right">{{ number_format($t->kg, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">{{ __('fuel.no_data') }}</td></tr>
                @endforelse
                </tbody>
                @if ($perTank->isNotEmpty())
                    <tfoot>
                    <tr class="font-weight-bold">
                        <td>{{ __('fuel.total') }}</td>
                        <td class="text-right">{{ $perTank->sum('supplies') }}</td>
                        <td class="text-right">{{ number_format($perTank->sum('liters'), 2) }}</td>
                        <td class="text-right">{{ number_format($perTank->sum('kg'), 2) }}</td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('menu.outbounds') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap" id="outbound-list">
                <thead>
                <tr>
                    <th>{{ __('fuel.supplied_at') }}</th>
                    <th>{{ __('fuel.tank_id') }}</th>
                    <th>{{ __('fuel.nozzle_id') }}</th>
                    <th>{{ __('fuel.mode') }}</th>
                    <th>{{ __('fuel.fuel_type') }}</th>
                    <th class="text-right">{{ __('fuel.liters') }}</th>
                    <th class="text-right">{{ __('fuel.amount_kg') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->supplied_at }}</td>
                        <td>{{ $row->nozzle && $row->nozzle->tank_id ? ($tankNames[$row->nozzle->tank_id] ?? $row->nozzle->tank_id) : '-' }}</td>
                        <td>{{ $nozzleLabels[$row->nozzle_id] ?? $row->nozzle_id }}</td>
                        <td>{{ __('fuel.mode_' . $row->mode) }}</td>
                        <td>{{ $fuels[$row->fuel_type] ?? $row->fuel_type }}</td>
                        <td class="text-right">{{ number_format($row->out_amount_lt, 2) }}</td>
                        <td class="text-right">{{ number_format($row->out_amount_kg, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">{{ __('fuel.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
