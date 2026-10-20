@extends('layouts.app')

@section('title', __('report.title_sales'))

@section('content')
    @include('report._period')

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('report.by_type') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('report.fuel_type') }}</th>
                    <th>{{ __('report.sale_type') }}</th>
                    <th>{{ __('report.usage') }}</th>
                    <th>{{ __('report.payment_method') }}</th>
                    <th class="text-right">{{ __('report.count') }}</th>
                    <th class="text-right">{{ __('report.amount_kg') }}</th>
                    <th class="text-right">{{ __('report.total_amount') }}</th>
                    <th class="text-right">{{ __('report.paid_amount') }}</th>
                    <th class="text-right">{{ __('report.unpaid_amount') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($summary as $r)
                    <tr>
                        <td>{{ $fuels[$r->fuel_type] ?? $r->fuel_type }}</td>
                        <td>{{ __('report.sale_type_' . $r->sale_type) }}</td>
                        <td>{{ __('report.usage_' . $r->usage) }}</td>
                        <td>{{ __('report.payment_' . $r->payment_method) }}</td>
                        <td class="text-right">{{ $r->sales }}</td>
                        <td class="text-right">{{ number_format($r->kg, 2) }}</td>
                        <td class="text-right">{{ number_format($r->total, 2) }}</td>
                        <td class="text-right">{{ number_format($r->paid, 2) }}</td>
                        <td class="text-right">{{ number_format($r->unpaid, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">{{ __('report.no_data') }}</td></tr>
                @endforelse
                </tbody>
                @if ($summary->isNotEmpty())
                    <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="4">{{ __('report.total') }}</td>
                        <td class="text-right">{{ $summary->sum('sales') }}</td>
                        <td class="text-right">{{ number_format($summary->sum('kg'), 2) }}</td>
                        <td class="text-right">{{ number_format($summary->sum('total'), 2) }}</td>
                        <td class="text-right">{{ number_format($summary->sum('paid'), 2) }}</td>
                        <td class="text-right">{{ number_format($summary->sum('unpaid'), 2) }}</td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('report.by_day') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('report.day') }}</th>
                    <th class="text-right">{{ __('report.count') }}</th>
                    <th class="text-right">{{ __('report.amount_kg') }}</th>
                    <th class="text-right">{{ __('report.total_amount') }}</th>
                    <th class="text-right">{{ __('report.paid_amount') }}</th>
                    <th class="text-right">{{ __('report.unpaid_amount') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($daily as $r)
                    <tr>
                        <td>{{ $r->day }}</td>
                        <td class="text-right">{{ $r->sales }}</td>
                        <td class="text-right">{{ number_format($r->kg, 2) }}</td>
                        <td class="text-right">{{ number_format($r->total, 2) }}</td>
                        <td class="text-right">{{ number_format($r->paid, 2) }}</td>
                        <td class="text-right">{{ number_format($r->unpaid, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
