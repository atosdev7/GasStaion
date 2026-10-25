@extends('layouts.app')

@section('title', __('menu.inbound'))

@section('actions')
    @can('inbound.manage')
        <a href="{{ route('inbound.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('fuel.new') }}</a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        {{-- every filter sits in the head of its own column; the inputs belong to this form by their form attribute --}}
        <form method="GET" action="{{ route('inbound.index') }}" id="inbound-filters"></form>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('fuel.inbound_date') }}</th>
                    <th>{{ __('fuel.from_name') }}</th>
                    <th>{{ __('fuel.tank_id') }}</th>
                    <th>{{ __('fuel.fuel_type') }}</th>
                    <th class="text-right">{{ __('fuel.amount_kg') }}</th>
                    <th class="text-right">{{ __('fuel.cost') }}</th>
                    <th>{{ __('fuel.is_default') }}</th>
                    <th>{{ __('fuel.note') }}</th>
                    @can('inbound.manage')
                        <th class="text-right" style="width: 1%">{{ __('fuel.actions') }}</th>
                    @endcan
                </tr>
                <tr class="filters">
                    <th><input name="inbound_date" form="inbound-filters" type="date" value="{{ $filters['inbound_date'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('inbound-filters').submit()"></th>
                    <th><input name="from_name" form="inbound-filters" type="text" maxlength="100" placeholder="{{ __('fuel.search') }}" value="{{ $filters['from_name'] ?? '' }}" class="form-control form-control-sm" style="min-width: 160px"></th>
                    <th colspan="3"></th>
                    <th><input name="cost" form="inbound-filters" type="number" step="0.0001" min="0" placeholder="{{ __('fuel.cost') }}" value="{{ $filters['cost'] ?? '' }}" class="form-control form-control-sm text-right" style="min-width: 110px"></th>
                    <th></th>
                    <th>
                        <input name="note" form="inbound-filters" type="text" maxlength="255" placeholder="{{ __('fuel.search') }}" value="{{ $filters['note'] ?? '' }}" class="form-control form-control-sm" style="min-width: 160px">
                        @cannot('inbound.manage')
                            <div class="mt-1"><button type="submit" form="inbound-filters" class="btn btn-sm btn-primary">{{ __('fuel.show') }}</button>
                            <a href="{{ route('inbound.index') }}" class="btn btn-sm btn-default">{{ __('fuel.clear_filters') }}</a></div>
                        @endcannot
                    </th>
                    @can('inbound.manage')
                        <th class="text-nowrap text-right">
                            <button type="submit" form="inbound-filters" class="btn btn-sm btn-primary">{{ __('fuel.show') }}</button>
                            <a href="{{ route('inbound.index') }}" class="btn btn-sm btn-default">{{ __('fuel.clear_filters') }}</a>
                        </th>
                    @endcan
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->inbound_date }}</td>
                        <td>{{ $row->from_name }}</td>
                        <td>{{ $tanks[$row->tank_id] ?? $row->tank_id }}</td>
                        <td>{{ $fuels[$row->fuel_type] ?? $row->fuel_type }}</td>
                        <td class="text-right">{{ number_format($row->amount_kg, 2) }}</td>
                        <td class="text-right">{{ number_format($row->cost, 4) }}</td>
                        <td>@if ($row->is_default)<span class="badge badge-success">{{ __('fuel.default') }}</span>@endif</td>
                        <td>{{ $row->note }}</td>
                        @can('inbound.manage')
                            <td class="text-right">
                                @unless ($row->is_default)
                                    <form method="POST" action="{{ route('inbound.default', $row->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('fuel.make_default') }}</button>
                                    </form>
                                @endunless
                                <a href="{{ route('inbound.edit', $row->id) }}" class="btn btn-sm btn-outline-primary">{{ __('fuel.edit') }}</a>
                                <form method="POST" action="{{ route('inbound.destroy', $row->id) }}" class="d-inline"
                                      onsubmit="return confirm(@json(__('fuel.inbound_delete_confirm')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('fuel.delete') }}</button>
                                </form>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">{{ __('fuel.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
