@extends('layouts.app')

@section('title', __('menu.ticket_inbound'))

@section('actions')
    @can('ticket_stock.manage')
        <a href="{{ route('ticket-inbound.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('tickets.new') }}</a>
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
        <form method="GET" action="{{ route('ticket-inbound.index') }}" id="ticket-inbound-filters"></form>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('tickets.inbound_date') }}</th>
                    <th>{{ __('tickets.fuel_type') }}</th>
                    <th class="text-right">{{ __('tickets.spec') }}</th>
                    <th class="text-right">{{ __('tickets.quantity') }}</th>
                    <th class="text-right">{{ __('tickets.cost') }}</th>
                    <th class="text-right">{{ __('tickets.cost_total') }}</th>
                    <th>{{ __('tickets.note') }}</th>
                    <th>{{ __('tickets.confirmation') }}</th>
                    @can('ticket_stock.manage')
                        <th class="text-right" style="width: 1%">{{ __('tickets.actions') }}</th>
                    @endcan
                </tr>
                <tr class="filters">
                    <th><input name="inbound_date" form="ticket-inbound-filters" type="date" value="{{ $filters['inbound_date'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('ticket-inbound-filters').submit()"></th>
                    <th>
                        <select name="fuel_type" form="ticket-inbound-filters" class="form-control form-control-sm" onchange="document.getElementById('ticket-inbound-filters').submit()">
                            <option value="">{{ __('tickets.all_fuel_types') }}</option>
                            @foreach ($fuels as $key => $name)
                                <option value="{{ $key }}" {{ ($filters['fuel_type'] ?? '') === (string) $key ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <select name="spec" form="ticket-inbound-filters" class="form-control form-control-sm" onchange="document.getElementById('ticket-inbound-filters').submit()">
                            <option value="">{{ __('tickets.all_specs') }}</option>
                            @foreach ($specs as $key => $kg)
                                <option value="{{ $key }}" {{ (string) ($filters['spec'] ?? '') === (string) $key ? 'selected' : '' }}>{{ $kg }} {{ __('tickets.kg') }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th colspan="5">
                        @cannot('ticket_stock.manage')
                            <button type="submit" form="ticket-inbound-filters" class="btn btn-sm btn-primary">{{ __('tickets.show') }}</button>
                            <a href="{{ route('ticket-inbound.index') }}" class="btn btn-sm btn-default">{{ __('tickets.clear_filters') }}</a>
                        @endcannot
                    </th>
                    @can('ticket_stock.manage')
                        <th class="text-nowrap text-right">
                            <button type="submit" form="ticket-inbound-filters" class="btn btn-sm btn-primary">{{ __('tickets.show') }}</button>
                            <a href="{{ route('ticket-inbound.index') }}" class="btn btn-sm btn-default">{{ __('tickets.clear_filters') }}</a>
                        </th>
                    @endcan
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->inbound_date }}</td>
                        <td>{{ $fuels[$row->fuel_type] ?? $row->fuel_type }}</td>
                        <td class="text-right">{{ config('ticket_specs.' . $row->spec) }} {{ __('tickets.kg') }}</td>
                        <td class="text-right">{{ $row->quantity }}</td>
                        <td class="text-right">{{ $row->cost !== null ? number_format($row->cost, 4) : '' }}</td>
                        <td class="text-right">{{ $row->cost !== null ? number_format($row->cost * $row->quantity, 2) : '' }}</td>
                        <td>{{ $row->note }}</td>
                        <td>@include('ticket_inbound._confirmation', ['row' => $row, 'date' => $row->inbound_date])</td>
                        @can('ticket_stock.manage')
                            <td class="text-right">
                                @if ($row->isConfirmed())
                                    <span class="text-muted" title="{{ __('tickets.confirmed_locked') }}"><i class="fas fa-lock"></i></span>
                                @else
                                    <a href="{{ route('ticket-inbound.edit', $row->id) }}" class="btn btn-sm btn-outline-primary">{{ __('tickets.edit') }}</a>
                                    <form method="POST" action="{{ route('ticket-inbound.destroy', $row->id) }}" class="d-inline"
                                          onsubmit="return confirm(@json(__('tickets.ticket_inbound_delete_confirm')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('tickets.delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">{{ __('tickets.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
@endsection
