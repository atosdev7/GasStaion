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
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('tickets.inbound_date') }}</th>
                    <th>{{ __('tickets.from_name') }}</th>
                    <th>{{ __('tickets.fuel_type') }}</th>
                    <th class="text-right">{{ __('tickets.spec') }}</th>
                    <th class="text-right">{{ __('tickets.quantity') }}</th>
                    <th>{{ __('tickets.note') }}</th>
                    @can('ticket_stock.manage')
                        <th class="text-right" style="width: 1%">{{ __('tickets.actions') }}</th>
                    @endcan
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row->inbound_date }}</td>
                        <td>{{ $row->from_name }}</td>
                        <td>{{ $fuels[$row->fuel_type] ?? $row->fuel_type }}</td>
                        <td class="text-right">{{ config('ticket_specs.' . $row->spec) }} {{ __('tickets.kg') }}</td>
                        <td class="text-right">{{ $row->quantity }}</td>
                        <td>{{ $row->note }}</td>
                        @can('ticket_stock.manage')
                            <td class="text-right">
                                <a href="{{ route('ticket-inbound.edit', $row->id) }}" class="btn btn-sm btn-outline-primary">{{ __('tickets.edit') }}</a>
                                <form method="POST" action="{{ route('ticket-inbound.destroy', $row->id) }}" class="d-inline"
                                      onsubmit="return confirm(@json(__('tickets.ticket_inbound_delete_confirm')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('tickets.delete') }}</button>
                                </form>
                            </td>
                        @endcan
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
