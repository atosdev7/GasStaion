@extends('layouts.app')

@section('title', __('report.title_cash'))

@section('content')
    @include('report._period')

    <div class="row">
        @foreach (['cash', 'bank'] as $method)
            @php($m = $rows->where('method', $method))
            <div class="col-md-6">
                <div class="info-box">
                    <span class="info-box-icon {{ $method === 'cash' ? 'bg-success' : 'bg-info' }}"><i class="fas {{ $method === 'cash' ? 'fa-money-bill' : 'fa-university' }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('report.payment_' . $method) }}</span>
                        <span class="info-box-number">{{ number_format($m->sum('sales') + $m->sum('payments'), 2) }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('report.day') }}</th>
                    <th>{{ __('report.payment_method') }}</th>
                    <th class="text-right">{{ __('report.from_sales') }}</th>
                    <th class="text-right">{{ __('report.from_payments') }}</th>
                    <th class="text-right">{{ __('report.total') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $r->day }}</td>
                        <td>{{ __('report.payment_' . $r->method) }}</td>
                        <td class="text-right">{{ number_format($r->sales, 2) }}</td>
                        <td class="text-right">{{ number_format($r->payments, 2) }}</td>
                        <td class="text-right">{{ number_format($r->sales + $r->payments, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">{{ __('report.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
