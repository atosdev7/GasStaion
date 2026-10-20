@extends('layouts.app')

@section('title', __('tickets.batch_tickets'))

@section('content')
    <div class="mb-2"><a href="{{ route('ticket-publish.index') }}"><i class="fas fa-arrow-left"></i> {{ __('menu.ticket_publish') }}</a></div>

    <div class="card">
        <div class="card-body">
            <strong>#{{ $batch->id }}</strong> &nbsp; {{ $batch->date }} &nbsp;
            {{ $fuels[$batch->fuel_type] ?? $batch->fuel_type }}, {{ config('ticket_specs.' . $batch->spec) }} {{ __('tickets.kg') }},
            {{ __('tickets.code') }} {{ $batch->code }}, {{ $batch->start_serial }} - {{ $batch->end_serial }} ({{ $batch->count }})
            <div class="mt-2">
                @foreach (['publish', 'sale', 'supply', 'restrict'] as $name)
                    <span class="badge badge-secondary mr-1">{{ __('tickets.ticket_status_' . $name) }}: {{ $perStatus[config('ticket_statuses.' . $name)] ?? 0 }}</span>
                @endforeach
            </div>
            @if ($touched)
                <div class="text-muted mt-2"><i class="fas fa-lock"></i> {{ $touched }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap" id="batch-tickets">
                <thead>
                <tr>
                    <th>{{ __('tickets.barcode') }}</th>
                    <th>{{ __('tickets.serial') }}</th>
                    <th>{{ __('tickets.status') }}</th>
                    <th>{{ __('tickets.ticket_downloaded') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($tickets as $t)
                    <tr>
                        <td>{{ $t->barcode }}</td>
                        <td>{{ $t->serial }}</td>
                        <td>{{ __('tickets.ticket_status_' . array_search((int) $t->status, config('ticket_statuses'))) }}</td>
                        <td>@if ($t->synced)<i class="fas fa-check text-success"></i>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $tickets->links() }}</div>
    </div>
@endsection
