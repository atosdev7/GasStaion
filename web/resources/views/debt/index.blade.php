@extends('layouts.app')

@section('title', __('menu.payments'))

@section('actions')
    @can('payments.manage')
        <a href="{{ route('payments.create') }}" class="btn btn-success"><i class="fas fa-hand-holding-usd"></i> {{ __('debts.record_payment') }}</a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-file-invoice-dollar"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('debts.total_owed') }}</span>
                    <span class="info-box-number">{{ number_format($totalOwed, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-hand-holding-usd"></i></span>
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
        <div class="card-header"><h3 class="card-title">{{ __('debts.debts') }}</h3></div>
        @if ($errors->any())
            <div class="card-body pb-0 text-danger">{{ $errors->first() }}</div>
        @endif
        {{-- every filter sits in the head of its own column; the inputs belong to this form by their form attribute --}}
        <form method="GET" action="{{ route('payments.index') }}" id="debt-filters"></form>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('debts.sale_date') }}</th>
                    <th>{{ __('debts.customer_id') }}</th>
                    <th>{{ __('debts.payment_method') }}</th>
                    <th class="text-right">{{ __('debts.amount') }}</th>
                    <th class="text-right">{{ __('debts.settled_amount') }}</th>
                    <th class="text-right">{{ __('debts.balance') }}</th>
                    <th>{{ __('debts.status') }}</th>
                    <th class="text-right">{{ __('debts.actions') }}</th>
                </tr>
                <tr class="filters">
                    <th><input name="sale_date" form="debt-filters" type="date" value="{{ $filters['sale_date'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('debt-filters').submit()"></th>
                    <th>
                        <select name="customer" form="debt-filters" id="debt-customer-filter" class="form-control form-control-sm js-select2" data-allow-clear style="min-width: 200px">
                            <option value="">{{ __('debts.all_customers') }}</option>
                            @foreach ($customers as $id => $name)
                                <option value="{{ $id }}" {{ (string) ($filters['customer'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <select name="payment_method" form="debt-filters" class="form-control form-control-sm" onchange="document.getElementById('debt-filters').submit()">
                            <option value="">{{ __('debts.all_payment_methods') }}</option>
                            @foreach (['cash', 'bank'] as $method)
                                <option value="{{ $method }}" {{ ($filters['payment_method'] ?? '') === $method ? 'selected' : '' }}>{{ __('debts.payment_' . $method) }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th colspan="3"></th>
                    <th>
                        <select name="status" form="debt-filters" class="form-control form-control-sm" onchange="document.getElementById('debt-filters').submit()">
                            <option value="">{{ __('debts.all_statuses') }}</option>
                            <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>{{ __('debts.debt_open') }}</option>
                            <option value="settled" {{ ($filters['status'] ?? '') === 'settled' ? 'selected' : '' }}>{{ __('debts.debt_settled') }}</option>
                        </select>
                    </th>
                    <th class="text-nowrap text-right">
                        <button type="submit" form="debt-filters" class="btn btn-sm btn-primary">{{ __('debts.show') }}</button>
                        <a href="{{ route('payments.index') }}" class="btn btn-sm btn-default">{{ __('debts.clear_filters') }}</a>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($debts as $debt)
                    <tr>
                        <td>{{ $debt->display_date ? $debt->display_date : __('debts.opening_balance') }}</td>
                        <td>{{ $customers[$debt->customer_id] ?? $debt->customer_id }}</td>
                        <td>{{ __('debts.payment_' . $debt->payment_method) }}</td>
                        <td class="text-right">{{ number_format($debt->net_amount, 2) }}</td>
                        <td class="text-right">{{ number_format($debt->settled_amount, 2) }}</td>
                        <td class="text-right">{{ number_format($debt->balance, 2) }}</td>
                        <td>
                            @if ($debt->balance < -0.005)
                                <span class="badge badge-info">{{ __('debts.debt_credit') }}</span>
                            @elseif ($debt->balance <= 0)
                                <span class="badge badge-success">{{ __('debts.debt_settled') }}</span>
                            @else
                                <span class="badge badge-warning">{{ __('debts.debt_open') }}</span>
                            @endif
                        </td>
                        <td class="text-nowrap text-right">
                            {{-- the actions of the debt in one drop-down: the sale it came from (an opening balance has none) --}}
                            @if ($debt->sale && auth()->user()->can('ticket_sales.view'))
                                <div class="btn-group debt-actions">
                                    <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">{{ __('debts.actions') }}</button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a href="{{ route('ticket-sales.show', $debt->sale_id) }}" class="dropdown-item"><i class="fas fa-eye fa-fw"></i> {{ __('debts.debt_view_sale') }}</a>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('debts.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $debts->links() }}</div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('debts.payments_received') }}</h3></div>
        <p class="text-muted px-3 pt-2 mb-0">{{ __('debts.payments_received_hint') }}</p>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('debts.payment_date') }}</th>
                    <th>{{ __('debts.customer_id') }}</th>
                    <th>{{ __('debts.paid_for') }}</th>
                    <th>{{ __('debts.payment_method') }}</th>
                    <th class="text-right">{{ __('debts.amount') }}</th>
                    <th>{{ __('debts.entered_by') }}</th>
                    <th>{{ __('debts.note') }}</th>
                    <th class="text-right">{{ __('debts.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>{{ substr((string) $payment->paid_date, 0, 10) }}</td>
                        <td>{{ $customers[$payment->customer_id] ?? $payment->customer_id }}</td>
                        <td>
                            @if ($payment->debt)
                                {{ $payment->debt->display_date ? substr((string) $payment->debt->display_date, 0, 10) : __('debts.opening_balance') }}
                            @else
                                <span class="text-muted">{{ __('debts.general_payment') }}</span>
                            @endif
                        </td>
                        <td>{{ __('debts.payment_' . $payment->payment_method) }}</td>
                        <td class="text-right {{ $payment->amount < 0 ? 'text-danger' : '' }}">{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ $users[$payment->user_id] ?? '' }}</td>
                        <td>{{ $payment->note }}</td>
                        <td class="text-nowrap text-right">
                            @if ($payment->reverses_payment_id)
                                <span class="badge badge-secondary">{{ __('debts.reversal_of', ['id' => $payment->reverses_payment_id]) }}</span>
                            @elseif (isset($reversed[$payment->id]))
                                <span class="badge badge-secondary">{{ __('debts.reversed') }}</span>
                            @else
                                @can('payments.manage')
                                    <form method="POST" action="{{ route('payments.reverse', $payment->id) }}" class="form-inline d-inline" onsubmit="return confirm(@json(__('debts.reverse_payment_confirm')))">
                                        @csrf
                                        <input type="text" name="note" maxlength="200" placeholder="{{ __('debts.reverse_note') }}" class="form-control form-control-sm mr-1" style="width: 140px">
                                        <button type="submit" class="btn btn-xs btn-outline-danger">{{ __('debts.reverse') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('debts.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $payments->links() }}</div>
    </div>

    @if ($unallocated->isNotEmpty())
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('debts.unallocated_payments') }}</h3></div>
            <p class="text-muted px-3">{{ __('debts.unallocated_payments_hint') }}</p>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th>{{ __('debts.customer_id') }}</th>
                        <th class="text-right">{{ __('debts.amount') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($unallocated as $row)
                        <tr>
                            <td>{{ $customers[$row->customer_id] ?? $row->customer_id }}</td>
                            <td class="text-right">{{ number_format($row->unallocated, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        $('#debt-customer-filter').on('change', function () { document.getElementById('debt-filters').submit(); });

        // The menu of the actions is fixed to the window (the table scrolls sideways and would cut it) and sits under its button.
        $(document).on('shown.bs.dropdown', '.debt-actions', function () {
            var button = $(this).find('.dropdown-toggle')[0].getBoundingClientRect();
            var menu = $(this).find('.dropdown-menu');
            menu.css({position: 'fixed', top: button.bottom + 'px', left: Math.max(4, button.right - menu.outerWidth()) + 'px', right: 'auto', transform: 'none'});
        });
        $(window).on('scroll resize', function () { $('.debt-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });
        $('.table-responsive').on('scroll', function () { $('.debt-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });
    </script>
@endpush
