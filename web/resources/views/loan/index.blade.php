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
    @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
    @if ($errors->has('settlement'))
        <div class="alert alert-danger">{{ $errors->first('settlement') }}</div>
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

    {{-- two tabs: the loans (each with its actions) and the tickets surrendered; a page of a list keeps its tab in the address (?tab=) --}}
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><a class="nav-link {{ $tab === 'loans' ? 'active' : '' }}" id="tab-loans-link" data-toggle="pill" href="#tab-loans" role="tab">{{ __('debts.tab_loans') }}</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'settlements' ? 'active' : '' }}" id="tab-settlements-link" data-toggle="pill" href="#tab-settlements" role="tab">{{ __('debts.tab_settlements') }}</a></li>
    </ul>
    <div class="tab-content">
    <div class="tab-pane fade {{ $tab === 'loans' ? 'show active' : '' }}" id="tab-loans" role="tabpanel">
    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('menu.ticket_loans') }}</h3></div>
        @if ($errors->any() && !$errors->has('settlement'))
            <div class="card-body pb-0 text-danger">{{ $errors->first() }}</div>
        @endif
        {{-- every filter sits in the head of its own column; the inputs belong to this form by their form attribute --}}
        <form method="GET" action="{{ route('loans.index') }}" id="loan-filters"><input type="hidden" name="tab" value="loans"></form>
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
                    <th class="text-right">{{ __('debts.actions') }}</th>
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
                    <th colspan="3"></th>
                    <th>
                        <select name="status" form="loan-filters" class="form-control form-control-sm" onchange="document.getElementById('loan-filters').submit()">
                            <option value="">{{ __('debts.all_statuses') }}</option>
                            <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>{{ __('debts.debt_open') }}</option>
                            <option value="settled" {{ ($filters['status'] ?? '') === 'settled' ? 'selected' : '' }}>{{ __('debts.debt_settled') }}</option>
                        </select>
                    </th>
                    <th class="text-nowrap text-right">
                        <button type="submit" form="loan-filters" class="btn btn-sm btn-primary">{{ __('debts.show') }}</button>
                        <a href="{{ route('loans.index') }}" class="btn btn-sm btn-default">{{ __('debts.clear_filters') }}</a>
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
                            @if ($loan->balance_kg < -0.005)
                                <span class="badge badge-info">{{ __('debts.debt_credit') }}</span>
                            @elseif ($loan->balance_kg <= 0)
                                <span class="badge badge-success">{{ __('debts.debt_settled') }}</span>
                            @else
                                <span class="badge badge-warning">{{ __('debts.debt_open') }}</span>
                            @endif
                        </td>
                        <td class="text-nowrap text-right">
                            {{-- the actions of the loan in one drop-down: its details in a popup, the settlement that pays it, the sale it came from (an opening loan has none) --}}
                            <div class="btn-group debt-actions">
                                <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">{{ __('debts.actions') }}</button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a href="{{ route('loans.show', $loan->id) }}" class="dropdown-item loan-detail"><i class="fas fa-eye fa-fw"></i> {{ __('debts.view') }}</a>
                                    @can('loans.manage')
                                        @if ($loan->balance_kg > 0.005)
                                            <a href="{{ route('loans.create', ['loan_id' => $loan->id]) }}" class="dropdown-item"><i class="fas fa-ticket-alt fa-fw"></i> {{ __('debts.settle') }}</a>
                                        @endif
                                    @endcan
                                    @if ($loan->sale_id && auth()->user()->can('ticket_sales.view'))
                                        <a href="{{ route('ticket-sales.show', $loan->sale_id) }}" class="dropdown-item"><i class="fas fa-receipt fa-fw"></i> {{ __('debts.debt_view_sale') }}</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('debts.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $loans->links() }}</div>
    </div>
    </div>

    <div class="tab-pane fade {{ $tab === 'settlements' ? 'show active' : '' }}" id="tab-settlements" role="tabpanel">
    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('debts.settlements') }}</h3></div>
        <p class="text-muted px-3 pt-2 mb-0">{{ __('debts.settlements_hint') }}</p>
        <form method="GET" action="{{ route('loans.index') }}" id="settlement-filters"><input type="hidden" name="tab" value="settlements"></form>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('debts.settled_on') }}</th>
                    <th>{{ __('debts.customer_id') }}</th>
                    <th>{{ __('debts.fuel_type') }}</th>
                    <th>{{ __('debts.ticket_barcode') }}</th>
                    <th class="text-right">{{ __('debts.amount_kg') }}</th>
                    <th>{{ __('debts.entered_by') }}</th>
                    <th class="text-right">{{ __('debts.actions') }}</th>
                </tr>
                <tr class="filters">
                    <th><input name="settled_on" form="settlement-filters" type="date" value="{{ $filters['settled_on'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('settlement-filters').submit()"></th>
                    <th>
                        <select name="set_customer" form="settlement-filters" id="settlement-customer-filter" class="form-control form-control-sm js-select2" data-allow-clear style="min-width: 200px">
                            <option value="">{{ __('debts.all_customers') }}</option>
                            @foreach ($customers as $id => $name)
                                <option value="{{ $id }}" {{ (string) ($filters['set_customer'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <select name="set_fuel" form="settlement-filters" class="form-control form-control-sm" onchange="document.getElementById('settlement-filters').submit()">
                            <option value="">{{ __('debts.all_fuel_types') }}</option>
                            @foreach ($fuels as $key => $name)
                                <option value="{{ $key }}" {{ ($filters['set_fuel'] ?? '') === (string) $key ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th><input name="barcode" form="settlement-filters" type="text" maxlength="16" autocomplete="off" value="{{ $filters['barcode'] ?? '' }}" placeholder="{{ __('debts.ticket_barcode') }}" class="form-control form-control-sm" style="min-width: 170px"></th>
                    <th colspan="2"></th>
                    <th class="text-nowrap text-right">
                        <button type="submit" form="settlement-filters" class="btn btn-sm btn-primary">{{ __('debts.show') }}</button>
                        <a href="{{ route('loans.index', ['tab' => 'settlements']) }}" class="btn btn-sm btn-default">{{ __('debts.clear_filters') }}</a>
                    </th>
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
                        <td>{{ $users[$settlement->user_id] ?? '' }}</td>
                        <td class="text-nowrap text-right">
                            <a href="{{ route('loans.settlements.show', $settlement->id) }}" class="btn btn-xs btn-outline-secondary mr-1 settlement-detail">{{ __('debts.view') }}</a>
                            @if ($settlement->reverses_settlement_id)
                                <a href="{{ route('loans.settlements.show', $settlement->reverses_settlement_id) }}" class="badge badge-secondary settlement-detail">{{ __('debts.settlement_reversal') }}</a>
                            @elseif (isset($reversedSettlements[$settlement->id]))
                                <a href="{{ route('loans.settlements.show', $reversedSettlements[$settlement->id]) }}" class="badge badge-secondary settlement-detail">{{ __('debts.reversed') }}</a>
                            @else
                                @can('loans.manage')
                                    @if ($settlement->isConfirmed())
                                        <span class="text-muted" title="{{ __('debts.settlement_confirmed_locked') }}"><i class="fas fa-lock"></i></span>
                                    @else
                                        <button type="button" class="btn btn-xs btn-outline-danger settlement-reverse" data-action="{{ route('loans.settlements.reverse', $settlement->id) }}">{{ __('debts.reverse') }}</button>
                                    @endif
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">{{ __('debts.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $settlements->links() }}</div>
    </div>
    </div>
    </div>

    {{-- reversing a settlement asks first; the reason (optional) is asked in the same window --}}
    @can('loans.manage')
        <div class="modal fade" id="reverse-modal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="" id="reverse-form" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('debts.reverse_settlement_title') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p>{{ __('debts.reverse_settlement_confirm') }}</p>
                        <div class="form-group mb-0">
                            <label for="reverse-note">{{ __('debts.reverse_note') }}</label>
                            <input type="text" id="reverse-note" name="note" maxlength="200" autocomplete="off" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('debts.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('debts.reverse') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="modal fade" id="loan-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loan-modal-title">{{ __('debts.loan_details') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="loan-modal-body"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $('#loan-customer-filter').on('change', function () { document.getElementById('loan-filters').submit(); });
        $('#settlement-customer-filter').on('change', function () { document.getElementById('settlement-filters').submit(); });

        // The menu of the actions is fixed to the window (the table scrolls sideways and would cut it) and sits under its button.
        $(document).on('shown.bs.dropdown', '.debt-actions', function () {
            var button = $(this).find('.dropdown-toggle')[0].getBoundingClientRect();
            var menu = $(this).find('.dropdown-menu');
            menu.css({position: 'fixed', top: button.bottom + 'px', left: Math.max(4, button.right - menu.outerWidth()) + 'px', right: 'auto', transform: 'none'});
        });
        $(window).on('scroll resize', function () { $('.debt-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });
        $('.table-responsive').on('scroll', function () { $('.debt-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });

        $(document).on('click', '.settlement-reverse', function () {
            $('#reverse-form').attr('action', $(this).data('action'));
            $('#reverse-note').val('');
            $('#reverse-modal').modal('show');
        });
        $('#reverse-modal').on('shown.bs.modal', function () { $('#reverse-note').trigger('focus'); });

        // the popup shows a loan or a settlement (the links inside a settlement popup change what it shows)
        $(document).on('click', 'a.loan-detail, a.settlement-detail', function (e) {
            e.preventDefault();
            var body = $('#loan-modal-body');
            $('#loan-modal-title').text($(this).hasClass('settlement-detail') ? @json(__('debts.settlement_details')) : @json(__('debts.loan_details')));
            body.text('...');
            $('#loan-modal').modal('show');
            $.get($(this).attr('href'), {modal: 1})
                .done(function (html) { body.html(html); })
                .fail(function () { body.text(@json(__('debts.loan_not_found'))); });
        });
    </script>
@endpush
