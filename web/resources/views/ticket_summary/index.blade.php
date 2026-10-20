@extends('layouts.app')

@section('title', __('menu.ticket_summary'))

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- the summary is of one fuel type and one spec: choose them, press Show, and the table comes below --}}
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('ticket-summary.index') }}" class="form-inline" id="summary-filters">
                <label for="f_fuel_type" class="mr-2">{{ __('tickets.fuel_type') }}</label>
                <select id="f_fuel_type" name="fuel_type" class="form-control mr-3">
                    @foreach ($fuels as $key => $name)
                        <option value="{{ $key }}" {{ (string) $fuelType === (string) $key ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                <label for="f_spec" class="mr-2">{{ __('tickets.spec') }}</label>
                <select id="f_spec" name="spec" class="form-control mr-3">
                    @foreach ($specs as $key => $kg)
                        <option value="{{ $key }}" {{ (string) $spec === (string) $key ? 'selected' : '' }}>{{ $kg }} {{ __('tickets.kg') }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">{{ __('tickets.show') }}</button>
            </form>
        </div>
    </div>

    @if ($chosen)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ $fuels[$fuelType] ?? $fuelType }}, {{ config('ticket_specs.' . $spec) }} {{ __('tickets.kg') }}</h3>
                <div class="card-tools">
                    <a href="{{ route('ticket-summary.index', ['fuel_type' => $fuelType, 'spec' => $spec, 'export' => 'xls']) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel"></i> {{ __('tickets.export_xls') }}</a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover table-bordered text-nowrap" id="ticket-summary" style="table-layout: fixed; min-width: 800px">
                    <colgroup>
                        <col style="width: 150px">  {{-- date --}}
                        <col style="width: 110px">  {{-- inbound: count --}}
                        <col style="width: 110px">  {{-- publish: count --}}
                        <col style="width: 150px">  {{-- publish: start --}}
                        <col style="width: 150px">  {{-- publish: end --}}
                        <col style="width: 130px">  {{-- balance --}}
                    </colgroup>
                    <thead>
                    <tr>
                        <th rowspan="2" class="align-middle">{{ __('tickets.date') }}</th>
                        <th class="text-center">{{ __('tickets.summary_in') }}</th>
                        <th colspan="3" class="text-center">{{ __('tickets.summary_publish') }}</th>
                        <th rowspan="2" class="align-middle text-right">{{ __('tickets.summary_balance') }}</th>
                    </tr>
                    <tr>
                        <th class="text-right">{{ __('tickets.summary_count') }}</th>
                        <th class="text-right">{{ __('tickets.summary_count') }}</th>
                        <th class="text-right">{{ __('tickets.summary_start') }}</th>
                        <th class="text-right">{{ __('tickets.summary_end') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>{{ $r->day }}</td>
                            <td class="text-right">{{ $r->in ?: '' }}</td>
                            <td class="text-right">{{ $r->out ?: '' }}</td>
                            <td class="text-right">{{ $r->first }}</td>
                            <td class="text-right">{{ $r->last }}</td>
                            <td class="text-right"><strong>{{ $r->balance }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">{{ __('tickets.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot>
                        <tr class="font-weight-bold">
                            <td>{{ __('tickets.total') }}</td>
                            <td class="text-right">{{ $totalIn }}</td>
                            <td class="text-right">{{ $totalOut }}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif
@endsection
