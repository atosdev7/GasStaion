@extends('layouts.app')

@section('title', __('menu.outbounds'))

@section('actions')
    @can('outbound.manage')
        <a href="{{ route('outbound.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('fuel.new') }}</a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

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
            <small class="form-text text-muted mt-2">{{ __('fuel.outbound_hint') }}</small>
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
                    <th class="text-right">{{ __('fuel.supplies') }}</th>
                    <th class="text-right">{{ __('fuel.liters') }}</th>
                    <th class="text-right">{{ __('fuel.amount_kg') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($perTank as $t)
                    <tr>
                        <td>{{ $t->tank_id ? ($tankNames[$t->tank_id] ?? $t->tank_id) : '-' }}</td>
                        <td class="text-right">{{ $t->count }}</td>
                        <td class="text-right">{{ $t->supplies }}</td>
                        <td class="text-right">{{ number_format($t->liters, 2) }}</td>
                        <td class="text-right">{{ number_format($t->kg, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">{{ __('fuel.no_data') }}</td></tr>
                @endforelse
                </tbody>
                @if ($perTank->isNotEmpty())
                    <tfoot>
                    <tr class="font-weight-bold">
                        <td>{{ __('fuel.total') }}</td>
                        <td class="text-right">{{ $perTank->sum('count') }}</td>
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
                    <th>{{ __('fuel.outbound_date') }}</th>
                    <th>{{ __('fuel.outbound_kind') }}</th>
                    <th>{{ __('fuel.tank_id') }}</th>
                    <th>{{ __('fuel.nozzle_id') }}</th>
                    <th>{{ __('fuel.mode') }}</th>
                    <th>{{ __('fuel.to_name') }}</th>
                    <th class="text-right">{{ __('fuel.liters') }}</th>
                    <th class="text-right">{{ __('fuel.amount_kg') }}</th>
                    <th>{{ __('fuel.note') }}</th>
                    @can('outbound.manage')
                        <th class="text-right" style="width: 1%">{{ __('fuel.actions') }}</th>
                    @endcan
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->outbound_date }}</td>
                        <td>{{ $row->isSupply() ? __('fuel.kind_supply') : __('fuel.kind_by_hand') }}</td>
                        <td>{{ $tankNames[$row->tank_id] ?? $row->tank_id }}</td>
                        <td>{{ $row->supplement ? ($nozzleLabels[$row->supplement->nozzle_id] ?? $row->supplement->nozzle_id) : '' }}</td>
                        <td>{{ $row->supplement ? __('fuel.mode_' . $row->supplement->mode) : '' }}</td>
                        <td>{{ $row->to_name }}</td>
                        <td class="text-right">{{ $row->supplement ? number_format($row->supplement->out_amount_lt, 2) : '' }}</td>
                        <td class="text-right">{{ number_format($row->amount_kg, 2) }}</td>
                        <td>{{ $row->note }}</td>
                        @can('outbound.manage')
                            <td class="text-right">
                                @unless ($row->isSupply())
                                    <a href="{{ route('outbound.edit', $row->id) }}" class="btn btn-sm btn-outline-primary">{{ __('fuel.edit') }}</a>
                                    <form method="POST" action="{{ route('outbound.destroy', $row->id) }}" class="d-inline"
                                          onsubmit="return confirm(@json(__('fuel.outbound_delete_confirm')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('fuel.delete') }}</button>
                                    </form>
                                @endunless
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-muted">{{ __('fuel.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
