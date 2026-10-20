@extends('layouts.app')

@section('title', __('menu.group_dashboard'))

@section('content')
    <p>{{ __('app.welcome', ['name' => auth()->user()->name]) }}</p>

    @if ($dashboard)
        @foreach ($tanks as $t)
            @if ($t->current < 0 || ($t->percent !== null && $t->percent <= $lowPercent))
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    {{ __('dashboard.low_tank', ['tank' => $t->tank->name, 'kg' => number_format($t->current, 2)]) }}
                </div>
            @endif
        @endforeach

        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ number_format($todayTotal, 2) }}</h3>
                        <p>{{ __('dashboard.today_sales', ['count' => $todaySales, 'kg' => number_format($todayKg, 2)]) }}</p>
                    </div>
                    <div class="icon"><i class="fas fa-cash-register"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ number_format($openDebt, 2) }}</h3>
                        <p>{{ __('dashboard.open_debt_balance') }}</p>
                    </div>
                    <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3>{{ number_format($openLoanKg, 2) }}</h3>
                        <p>{{ __('dashboard.open_loan_kg') }}</p>
                    </div>
                    <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $ticketsReady }}</h3>
                        <p>{{ __('dashboard.ready_to_sell') }}</p>
                    </div>
                    <div class="icon"><i class="fas fa-ticket-alt"></i></div>
                </div>
            </div>
        </div>

        @include('dashboard._compare')

        <div class="row">
            <div class="col-xl-6">@include('dashboard._tank_stock')</div>
            <div class="col-xl-6">@include('dashboard._ticket_stock')</div>
        </div>
        <div class="row">
            <div class="col-xl-6">@include('dashboard._sales_days')</div>
            <div class="col-xl-6">@include('dashboard._tank_trend')</div>
        </div>
    @endif
@endsection
