@extends('layouts.app')

@section('title', __('report.title_tanks'))

@section('content')
    <div class="mb-2 clearfix">
        <a href="{{ route('reports.index') }}"><i class="fas fa-arrow-left"></i> {{ __('report.all_reports') }}</a>
        <a href="{{ url()->current() }}?export=xls" class="btn btn-outline-success btn-sm float-right"><i class="fas fa-file-excel"></i> {{ __('report.export_xls') }}</a>
    </div>

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('report.tank_id') }}</th>
                    <th>{{ __('report.fuel_type') }}</th>
                    <th class="text-right">{{ __('report.opening') }}</th>
                    <th class="text-right">{{ __('report.inbound') }}</th>
                    <th class="text-right">{{ __('report.outbound') }}</th>
                    <th class="text-right">{{ __('report.current') }}</th>
                    <th class="text-right">{{ __('report.capacity') }}</th>
                    <th class="text-right">{{ __('report.percent_full') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $r->tank->name }}</td>
                        <td>{{ $fuels[$r->tank->fuel_type] ?? $r->tank->fuel_type }}</td>
                        <td class="text-right">{{ number_format($r->opening, 2) }}</td>
                        <td class="text-right">{{ number_format($r->inbound, 2) }}</td>
                        <td class="text-right">{{ number_format($r->supplied, 2) }}</td>
                        <td class="text-right font-weight-bold {{ $r->current < 0 ? 'text-danger' : '' }}">{{ number_format($r->current, 2) }}</td>
                        <td class="text-right">{{ $r->capacity !== null ? number_format($r->capacity, 2) : '-' }}</td>
                        <td class="text-right">{{ $r->percent !== null ? $r->percent . '%' : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('report.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted">{{ __('report.tanks_hint') }}</div>
    </div>

    @if (count($layers))
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('report.tank_layers') }}</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm text-nowrap" id="tank-layers">
                    <thead>
                    <tr>
                        <th>{{ __('report.tank_id') }}</th>
                        <th>{{ __('report.fuel_type') }}</th>
                        <th class="text-right">{{ __('report.layer_cost') }}</th>
                        <th class="text-right">{{ __('report.layer_kg') }}</th>
                        <th class="text-right">{{ __('report.layer_value') }}</th>
                        <th>{{ __('report.layer_since') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $r)
                        @foreach ($layers[$r->tank->id] ?? [] as $layer)
                            <tr>
                                <td>{{ $r->tank->name }}</td>
                                <td>{{ $fuels[$r->tank->fuel_type] ?? $r->tank->fuel_type }}</td>
                                <td class="text-right">{{ $layer['cost'] !== null ? number_format($layer['cost'], 4) : '-' }}</td>
                                <td class="text-right {{ $layer['kg'] < 0 ? 'text-danger' : '' }}">{{ number_format($layer['kg'], 2) }}</td>
                                <td class="text-right">{{ $layer['cost'] !== null ? number_format($layer['kg'] * $layer['cost'], 2) : '-' }}</td>
                                <td>{{ $layer['date'] }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted">{{ __('report.tank_layers_hint') }}</div>
        </div>
    @endif
@endsection