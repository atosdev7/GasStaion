@extends('layouts.app')

@section('title', __('report.title_profit'))

@section('content')
    @include('report._period')

    @if ($rows->sum('missing_cost') > 0)
        <div class="alert alert-warning">{{ __('report.missing_cost_hint') }}</div>
    @endif

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('report.fuel_type') }}</th>
                    <th>{{ __('report.usage') }}</th>
                    <th class="text-right">{{ __('report.amount_kg') }}</th>
                    <th class="text-right">{{ __('report.revenue') }}</th>
                    <th class="text-right">{{ __('report.cost') }}</th>
                    <th class="text-right">{{ __('report.profit') }}</th>
                    <th class="text-right">{{ __('report.missing_cost') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $fuels[$r->fuel_type] ?? $r->fuel_type }}</td>
                        <td>{{ __('report.usage_' . $r->usage) }}</td>
                        <td class="text-right">{{ number_format($r->kg, 2) }}</td>
                        <td class="text-right">{{ number_format($r->revenue, 2) }}</td>
                        <td class="text-right">{{ number_format($r->cost_total, 2) }}</td>
                        <td class="text-right {{ $r->profit < 0 ? 'text-danger' : '' }}">{{ number_format($r->profit, 2) }}</td>
                        <td class="text-right">{{ $r->missing_cost ?: '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">{{ __('report.no_data') }}</td></tr>
                @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="2">{{ __('report.total') }}</td>
                        <td class="text-right">{{ number_format($rows->sum('kg'), 2) }}</td>
                        <td class="text-right">{{ number_format($rows->sum('revenue'), 2) }}</td>
                        <td class="text-right">{{ number_format($rows->sum('cost_total'), 2) }}</td>
                        <td class="text-right">{{ number_format($rows->sum('profit'), 2) }}</td>
                        <td class="text-right">{{ $rows->sum('missing_cost') ?: '' }}</td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="card-footer text-muted">{{ __('report.profit_usage_hint') }}</div>
    </div>
@endsection
