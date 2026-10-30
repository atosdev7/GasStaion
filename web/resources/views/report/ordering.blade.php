@extends('layouts.app')

@section('title', __('report.title_ordering'))

@section('content')
    <div class="mb-2 clearfix">
        <a href="{{ route('reports.index') }}"><i class="fas fa-arrow-left"></i> {{ __('report.all_reports') }}</a>
        <a href="{{ url()->current() }}?export=xls" class="btn btn-outline-success btn-sm float-right"><i class="fas fa-file-excel"></i> {{ __('report.export_xls') }}</a>
    </div>

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap" id="ordering-table">
                <thead>
                <tr>
                    <th rowspan="2">{{ __('report.fuel_type') }}</th>
                    <th rowspan="2" class="text-right">{{ __('report.ord_tanks') }}</th>
                    <th rowspan="2" class="text-right">{{ __('report.ord_system_owed') }}</th>
                    <th rowspan="2" class="text-right">{{ __('report.ord_old_owed') }}</th>
                    <th rowspan="2" class="text-right">{{ __('report.ord_card_owed') }}</th>
                    <th rowspan="2" class="text-right">{{ __('report.ord_owed') }}</th>
                    <th rowspan="2" class="text-right">{{ __('report.ord_free') }}</th>
                    <th colspan="{{ count($windows) }}" class="text-center">{{ __('report.ord_group_supplied') }}</th>
                    <th colspan="{{ count($windows) }}" class="text-center">{{ __('report.ord_group_sold') }}</th>
                    <th colspan="{{ count($windows) }}" class="text-center">{{ __('report.ord_group_cover') }}</th>
                </tr>
                <tr>
                    @foreach (range(1, 3) as $group)
                        @foreach ($windows as $days)
                            <th class="text-right">{{ $days }} {{ __('report.ord_days_short') }}</th>
                        @endforeach
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr class="ordering-{{ $r->fuel_type }}">
                        <td>{{ $fuels[$r->fuel_type] ?? $r->fuel_type }}</td>
                        <td class="text-right">{{ number_format($r->tanks, 2) }}</td>
                        <td class="text-right">{{ number_format($r->system_owed, 2) }}</td>
                        <td class="text-right">{{ number_format($r->old_owed, 2) }}</td>
                        <td class="text-right">{{ number_format($r->card_owed, 2) }}</td>
                        <td class="text-right">{{ number_format($r->owed, 2) }}</td>
                        <td class="text-right font-weight-bold {{ $r->free < 0 ? 'text-danger' : '' }}">{{ number_format($r->free, 2) }}</td>
                        @foreach ($windows as $days)
                            <td class="text-right">{{ number_format($r->supplied[$days], 2) }}</td>
                        @endforeach
                        @foreach ($windows as $days)
                            <td class="text-right">{{ number_format($r->sold[$days], 2) }}</td>
                        @endforeach
                        @foreach ($windows as $days)
                            <td class="text-right">{{ $r->cover[$days] === null ? '-' : number_format($r->cover[$days], 1) }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted">{{ __('report.ord_hint') }}</div>
    </div>
@endsection
