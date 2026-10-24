@extends('layouts.app')

@section('title', __('menu.ticket_loans'))

@section('actions')
    @can('loans.manage')
        <a href="{{ route('loans.create') }}" class="btn btn-success"><i class="fas fa-ticket-alt"></i> {{ __('debts.record_settlement') }}</a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-hand-holding-usd"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('debts.total_loaned') }}</span>
                    <span class="info-box-number">{{ number_format($totalLoaned, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-ticket-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('debts.total_settled') }}</span>
                    <span class="info-box-number">{{ number_format($totalSettled, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box">
                <span class="info-box-icon bg-warning"><i class="fas fa-balance-scale"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('debts.total_balance') }}</span>
                    <span class="info-box-number">{{ number_format($totalBalance, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('menu.ticket_loans') }}</h3></div>
        @if ($errors->any())
            <div class="card-body pb-0 text-danger">{{ $errors->first() }}</div>
        @endif
        {{-- every filter sits in the head of its own column; the inputs belong to this form by their form attribute --}}
        <form method="GET" action="{{ route('loans.index') }}" id="loan-filters"></form>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('debts.loaned_at') }}</th>
                    <th>{{ __('debts.customer_id') }}</th>
                    <th>{{ __('debts.fuel_type') }}</th>
                    <th class="text-right">{{ __('debts.amount_kg') }}</th>
                    <th class="text-right">{{ __('debts.settled_amount') }}</th>
                    <th class="text-right">{{ __('debts.balance') }}</th>
                    <th>{{ __('debts.status') }}</th>
                </tr>
                <tr class="filters">
                    <th><input name="loan_date" form="loan-filters" type="date" value="{{ $filters['loan_date'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('loan-filters').submit()"></th>
                    <th>
                        <select name="customer" form="loan-filters" id="loan-customer-filter" class="form-control form-control-sm js-select2" data-allow-clear style="min-width: 200px">
                            <option value="">{{ __('debts.all_customers') }}</option>
                            @foreach ($customers as $id => $name)
                                <option value="{{ $id }}" {{ (string) ($filters['customer'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <select name="fuel_type" form="loan-filters" class="form-control form-control-sm" onchange="document.getElementById('loan-filters').submit()">
                            <option value="">{{ __('debts.all_fuel_types') }}</option>
                            @foreach ($fuels as $key => $name)
                                <option value="{{ $key }}" {{ ($filters['fuel_type'] ?? '') === (string) $key ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th colspan="3" class="text-right text-nowrap">
                        <button type="submit" form="loan-filters" class="btn btn-sm btn-primary">{{ __('debts.show') }}</button>
                        <a href="{{ route('loans.index') }}" class="btn btn-sm btn-default">{{ __('debts.clear_filters') }}</a>
                    </th>
                    <th>
                        <select name="status" form="loan-filters" class="form-control form-control-sm" onchange="document.getElementById('loan-filters').submit()">
                            <option value="">{{ __('debts.all_statuses') }}</option>
                            <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>{{ __('debts.debt_open') }}</option>
                            <option value="settled" {{ ($filters['status'] ?? '') === 'settled' ? 'selected' : '' }}>{{ __('debts.debt_settled') }}</option>
                        </select>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($loans as $loan)
                    <tr>
                        <td>{{ $loan->loaned_at }}</td>
                        <td>{{ $customers[$loan->customer_id] ?? $loan->customer_id }}</td>
                        <td>{{ $fuels[$loan->fuel_type] ?? $loan->fuel_type }}</td>
                        <td class="text-right">{{ number_format($loan->amount_kg, 2) }}</td>
                        <td class="text-right">{{ number_format($loan->settled_amount_kg, 2) }}</td>
                        <td class="text-right">{{ number_format($loan->balance_kg, 2) }}</td>
                        <td>
                            @if ($loan->balance_kg <= 0)
                                <span class="badge badge-success">{{ __('debts.debt_settled') }}</span>
                            @else
                                <span class="badge badge-warning">{{ __('debts.debt_open') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">{{ __('debts.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $loans->links() }}</div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('debts.settlements') }}</h3></div>
        <p class="text-muted px-3 pt-2 mb-0">{{ __('debts.settlements_hint') }}</p>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('debts.settled_on') }}</th>
                    <th>{{ __('debts.customer_id') }}</th>
                    <th>{{ __('debts.fuel_type') }}</th>
                    <th>{{ __('debts.ticket_barcode') }}</th>
                    <th class="text-right">{{ __('debts.amount_kg') }}</th>
                    <th class="text-right">{{ __('debts.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($settlements as $settlement)
                    <tr>
                        <td>{{ substr((string) ($settlement->date ?: $settlement->settled_at), 0, 10) }}</td>
                        <td>{{ $settlement->loan ? ($customers[$settlement->loan->customer_id] ?? $settlement->loan->customer_id) : '' }}</td>
                        <td>{{ $settlement->loan ? ($fuels[$settlement->loan->fuel_type] ?? $settlement->loan->fuel_type) : '' }}</td>
                        <td>{{ $settlement->ticket_barcode }}</td>
                        <td class="text-right {{ $settlement->amount_kg < 0 ? 'text-danger' : '' }}">{{ number_format($settlement->amount_kg, 2) }}</td>
                        <td class="text-nowrap text-right">
                            @if ($settlement->reverses_settlement_id)
                                <span class="badge badge-secondary">{{ __('debts.settlement_reversal') }}</span>
                            @elseif (isset($reversedSettlements[$settlement->id]))
                                <span class="badge badge-secondary">{{ __('debts.reversed') }}</span>
                            @else
                                @can('loans.manage')
                                    <form method="POST" action="{{ route('loans.settlements.reverse', $settlement->id) }}" class="d-inline" onsubmit="return confirm(@json(__('debts.reverse_settlement_confirm')))">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-outline-danger">{{ __('debts.reverse') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">{{ __('debts.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $settlements->links() }}</div>
    </div>
@endsection

@push('scripts')
    <script>
        $('#loan-customer-filter').on('change', function () { document.getElementById('loan-filters').submit(); });
    </script>
@endpush
