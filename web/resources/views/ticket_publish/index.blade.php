@extends('layouts.app')

@section('title', __('menu.ticket_publish'))

@section('actions')
    @can('ticket_stock.manage')
        <a href="{{ route('ticket-publish.create') }}" class="btn btn-success"><i class="fas fa-print"></i> {{ __('tickets.publish') }}</a>
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
        <div class="card-header"><h3 class="card-title">{{ __('tickets.recent_batches') }}</h3></div>
        {{-- every filter sits in the head of its own column; the inputs belong to this form by their form attribute --}}
        <form method="GET" action="{{ route('ticket-publish.index') }}" id="ticket-publish-filters"></form>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('tickets.date') }}</th>
                    <th>{{ __('tickets.fuel_type') }}</th>
                    <th class="text-right">{{ __('tickets.spec') }}</th>
                    <th>{{ __('tickets.code') }}</th>
                    <th class="text-right">{{ __('tickets.start_serial') }}</th>
                    <th class="text-right">{{ __('tickets.end_serial') }}</th>
                    <th class="text-right">{{ __('tickets.quantity') }}</th>
                    <th>{{ __('tickets.confirmation') }}</th>
                    <th class="text-right" style="width: 1%">{{ __('tickets.actions') }}</th>
                </tr>
                <tr class="filters">
                    <th></th>
                    <th><input name="date" form="ticket-publish-filters" type="date" value="{{ $filters['date'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('ticket-publish-filters').submit()"></th>
                    <th>
                        <select name="fuel_type" form="ticket-publish-filters" class="form-control form-control-sm" onchange="document.getElementById('ticket-publish-filters').submit()">
                            <option value="">{{ __('tickets.all_fuel_types') }}</option>
                            @foreach ($fuels as $key => $name)
                                <option value="{{ $key }}" {{ ($filters['fuel_type'] ?? '') === (string) $key ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <select name="spec" form="ticket-publish-filters" class="form-control form-control-sm" onchange="document.getElementById('ticket-publish-filters').submit()">
                            <option value="">{{ __('tickets.all_specs') }}</option>
                            @foreach ($specs as $key => $kg)
                                <option value="{{ $key }}" {{ (string) ($filters['spec'] ?? '') === (string) $key ? 'selected' : '' }}>{{ $kg }} {{ __('tickets.kg') }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th colspan="5"></th>
                    <th class="text-nowrap text-right">
                        <button type="submit" form="ticket-publish-filters" class="btn btn-sm btn-primary">{{ __('tickets.show') }}</button>
                        <a href="{{ route('ticket-publish.index') }}" class="btn btn-sm btn-default">{{ __('tickets.clear_filters') }}</a>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($batches as $b)
                    <tr>
                        <td>{{ $b->id }}</td>
                        <td>{{ $b->date }}</td>
                        <td>{{ $fuels[$b->fuel_type] ?? $b->fuel_type }}</td>
                        <td class="text-right">{{ config('ticket_specs.' . $b->spec) }} {{ __('tickets.kg') }}</td>
                        <td>{{ $b->code }}</td>
                        <td class="text-right">{{ $b->start_serial }}</td>
                        <td class="text-right">{{ $b->end_serial }}</td>
                        <td class="text-right">{{ $b->count }}</td>
                        <td>@include('ticket_inbound._confirmation', ['row' => $b, 'date' => $b->date])</td>
                        <td class="text-right">
                            <a href="{{ route('ticket-publish.show', $b->id) }}" class="btn btn-sm btn-outline-secondary">{{ __('tickets.view_tickets') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-muted">{{ __('tickets.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $batches->links() }}</div>
    </div>
@endsection
