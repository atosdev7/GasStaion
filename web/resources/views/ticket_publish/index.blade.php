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
                    <th class="text-right" style="width: 1%">{{ __('tickets.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($batches as $b)
                    <tr>
                        <td>{{ $b->id }}</td>
                        <td>{{ $b->date }}</td>
                        <td>{{ $fuels[$b->fuel_type] ?? $b->fuel_type }}</td>
                        <td class="text-right">{{ config('ticket_specs.' . $b->spec) }} {{ __('tickets.kg') }}</td>
                        <td>{{ $b->code }}</td>
                        <td class="text-right">{{ $b->start_serial }}</td>
                        <td class="text-right">{{ $b->end_serial }}</td>
                        <td class="text-right">{{ $b->count }}</td>
                        <td class="text-right">
                            <a href="{{ route('ticket-publish.show', $b->id) }}" class="btn btn-sm btn-outline-secondary">{{ __('tickets.view_tickets') }}</a>
                            @can('ticket_stock.manage')
                                <a href="{{ route('ticket-publish.edit', $b->id) }}" class="btn btn-sm btn-outline-primary">{{ __('tickets.edit') }}</a>
                                <form method="POST" action="{{ route('ticket-publish.destroy', $b->id) }}" class="d-inline"
                                      onsubmit="return confirm(@json(__('tickets.batch_delete_confirm')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('tickets.delete') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
