@extends('layouts.app')

@section('title', __('report.title_customer') . ($customer ? ': ' . $customer : ''))

@section('content')
    @include('report._period')

    @if ($customer)
        <div class="row">
            <div class="col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-file-invoice-dollar"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('report.open_debt_balance') }}</span>
                        <span class="info-box-number">{{ number_format($debts->sum('balance'), 2) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-secondary"><i class="fas fa-coins"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('report.unallocated') }}</span>
                        <span class="info-box-number">{{ number_format($unallocated, 2) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-hand-holding-usd"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('report.open_loan_kg') }}</span>
                        <span class="info-box-number">{{ number_format($loans->sum('balance_kg'), 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('report.sales_in_period') }}</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th>{{ __('report.sale_date') }}</th>
                        <th>{{ __('report.fuel_type') }}</th>
                        <th>{{ __('report.sale_type') }}</th>
                        <th>{{ __('report.usage') }}</th>
                        <th class="text-right">{{ __('report.amount_kg') }}</th>
                        <th class="text-right">{{ __('report.total_amount') }}</th>
                        <th class="text-right">{{ __('report.paid_amount') }}</th>
                        <th class="text-right">{{ __('report.unpaid_amount') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($sales as $s)
                        <tr>
                            <td>{{ $s->sale_date }}</td>
                            <td>{{ $fuels[$s->fuel_type] ?? $s->fuel_type }}</td>
                            <td>{{ __('report.sale_type_' . $s->sale_type) }}</td>
                            <td>{{ __('report.usage_' . $s->usage) }}</td>
                            <td class="text-right">{{ number_format($s->amount_kg, 2) }}</td>
                            <td class="text-right">{{ number_format($s->total_amount, 2) }}</td>
                            <td class="text-right">{{ number_format($s->paid_amount, 2) }}</td>
                            <td class="text-right">{{ number_format($s->unpaid_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">{{ __('report.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('report.payments_in_period') }}</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th>{{ __('report.paid_date') }}</th>
                        <th>{{ __('report.payment_method') }}</th>
                        <th class="text-right">{{ __('report.amount') }}</th>
                        <th>{{ __('report.note') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($payments as $p)
                        <tr>
                            <td>{{ $p->paid_date }}</td>
                            <td>{{ __('report.payment_' . $p->payment_method) }}</td>
                            <td class="text-right">{{ number_format($p->amount, 2) }}</td>
                            <td>{{ $p->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">{{ __('report.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('report.debts') }}</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th>{{ __('report.sale_date') }}</th>
                        <th>{{ __('report.payment_method') }}</th>
                        <th class="text-right">{{ __('report.amount') }}</th>
                        <th class="text-right">{{ __('report.settled_amount') }}</th>
                        <th class="text-right">{{ __('report.balance') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($debts as $d)
                        <tr>
                            <td>{{ $d->display_date ? $d->display_date : __('report.opening_balance') }}</td>
                            <td>{{ __('report.payment_' . $d->payment_method) }}</td>
                            <td class="text-right">{{ number_format($d->amount, 2) }}</td>
                            <td class="text-right">{{ number_format($d->settled, 2) }}</td>
                            <td class="text-right">{{ number_format($d->balance, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('report.loans') }}</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th>{{ __('report.loaned_at') }}</th>
                        <th>{{ __('report.fuel_type') }}</th>
                        <th class="text-right">{{ __('report.amount_kg') }}</th>
                        <th class="text-right">{{ __('report.settled_amount') }}</th>
                        <th class="text-right">{{ __('report.balance') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($loans as $l)
                        <tr>
                            <td>{{ $l->sale_id ? $l->loaned_at : __('report.opening_balance') }}</td>
                            <td>{{ $fuels[$l->fuel_type] ?? $l->fuel_type }}</td>
                            <td class="text-right">{{ number_format($l->amount_kg, 2) }}</td>
                            <td class="text-right">{{ number_format($l->settled_amount_kg, 2) }}</td>
                            <td class="text-right">{{ number_format($l->balance_kg, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
