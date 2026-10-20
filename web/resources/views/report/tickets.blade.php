@extends('layouts.app')

@section('title', __('report.title_tickets'))

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
                    <th>{{ __('report.fuel_type') }}</th>
                    <th>{{ __('report.spec') }}</th>
                    <th class="text-right">{{ __('report.received') }}</th>
                    <th class="text-right">{{ __('report.blank') }}</th>
                    <th class="text-right">{{ __('report.unsold') }}</th>
                    <th class="text-right">{{ __('report.sold') }}</th>
                    <th class="text-right">{{ __('report.supplied') }}</th>
                    <th class="text-right">{{ __('report.restricted') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $fuels[$r->fuel_type] ?? $r->fuel_type }}</td>
                        <td>{{ $r->spec === null ? __('report.direct') : config('ticket_specs.' . $r->spec) . ' ' . __('report.kg') }}</td>
                        <td class="text-right">{{ $r->received ?? '-' }}</td>
                        <td class="text-right">{{ $r->blank ?? '-' }}</td>
                        <td class="text-right">{{ $r->publish }}</td>
                        <td class="text-right">{{ $r->sale }}</td>
                        <td class="text-right">{{ $r->supply }}</td>
                        <td class="text-right">{{ $r->restrict }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('report.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
