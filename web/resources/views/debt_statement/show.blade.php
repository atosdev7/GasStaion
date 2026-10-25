@extends('layouts.app')

@section('title', __('menu.group_debts') . ' - ' . __('menu.debt_statement'))

@section('content')
    <div class="card no-print">
        <div class="card-body">
            <form method="GET" action="{{ route('debt-statement.show') }}" class="form-inline">
                <label for="f_customer" class="mr-2">{{ __('debts.customer_id') }}</label>
                <select id="f_customer" name="customer" class="form-control js-select2 mr-3" style="min-width: 220px">
                    <option value=""></option>
                    @foreach ($customers as $id => $name)
                        <option value="{{ $id }}" {{ $customer && (int) $customer['id'] === (int) $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                <label for="f_from" class="mr-2">{{ __('debts.from') }}</label>
                <input id="f_from" name="from" type="date" value="{{ $from }}" class="form-control mr-3">
                <label for="f_to" class="mr-2">{{ __('debts.to') }}</label>
                <input id="f_to" name="to" type="date" value="{{ $to }}" class="form-control mr-3">
                <button type="submit" class="btn btn-primary">{{ __('debts.show') }}</button>
                @if ($customer)
                    <button type="button" class="btn btn-outline-secondary ml-auto" onclick="window.print()"><i class="fas fa-print"></i> {{ __('debts.statement_print') }}</button>
                @endif
            </form>
            @if ($errors->any())
                <div class="text-danger mt-2">{{ $errors->first() }}</div>
            @endif
            <small class="form-text text-muted">{{ __('debts.statement_hint') }}</small>
        </div>
    </div>

    @if (!$customer)
        <div class="text-muted">{{ __('debts.statement_choose') }}</div>
    @else
        <div class="card" id="statement">
            <div class="card-header">
                <h3 class="card-title">{{ __('debts.statement_for', ['name' => $customer['name']]) }} &middot; {{ $from }} - {{ $to }}</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm text-nowrap mb-0" id="statement-table">
                    <thead>
                    <tr>
                        <th>{{ __('debts.statement_date') }}</th>
                        <th>{{ __('debts.statement_what') }}</th>
                        <th>{{ __('debts.payment_method') }}</th>
                        <th>{{ __('debts.note') }}</th>
                        <th class="text-right">{{ __('debts.amount') }}</th>
                        <th class="text-right">{{ __('debts.balance') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="font-weight-bold statement-opening">
                        <td>{{ $from }}</td>
                        <td colspan="3">{{ __('debts.statement_opening') }}</td>
                        <td></td>
                        <td class="text-right">{{ number_format($opening, 2) }}</td>
                    </tr>
                    @forelse ($lines as $line)
                        <tr class="statement-line">
                            <td>{{ $line['day'] }}</td>
                            <td>{{ __('debts.statement_' . ($line['kind'] === 'opening' ? 'opening_balance' : $line['kind'])) }}</td>
                            <td>{{ $line['method'] ? __('debts.payment_' . $line['method']) : '' }}</td>
                            <td>{{ $line['note'] }}</td>
                            <td class="text-right {{ $line['amount'] < 0 ? 'text-success' : '' }}">{{ number_format($line['amount'], 2) }}</td>
                            <td class="text-right">{{ number_format($line['balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">{{ __('debts.statement_no_lines') }}</td></tr>
                    @endforelse
                    <tr class="font-weight-bold statement-closing">
                        <td>{{ $to }}</td>
                        <td colspan="3">{{ __('debts.statement_closing') }}</td>
                        <td></td>
                        <td class="text-right">{{ number_format($closing, 2) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" id="statement-open">
            <div class="card-header"><h3 class="card-title">{{ __('debts.statement_open_debts') }}</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm text-nowrap mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('debts.statement_date') }}</th>
                        <th>{{ __('debts.payment_method') }}</th>
                        <th class="text-right">{{ __('debts.amount') }}</th>
                        <th class="text-right">{{ __('debts.settled_amount') }}</th>
                        <th class="text-right">{{ __('debts.balance') }}</th>
                        <th class="text-right">{{ __('debts.statement_age') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($open as $debt)
                        <tr class="statement-open-debt">
                            <td>{{ $debt->day }}@if (!$debt->sale_id) ({{ __('debts.opening_balance') }})@endif</td>
                            <td>{{ __('debts.payment_' . $debt->payment_method) }}</td>
                            <td class="text-right">{{ number_format($debt->net_amount, 2) }}</td>
                            <td class="text-right">{{ number_format($debt->settled, 2) }}</td>
                            <td class="text-right">{{ number_format($debt->balance, 2) }}</td>
                            <td class="text-right">{{ $debt->age }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">{{ __('debts.statement_none_open') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if ($open->isNotEmpty())
                        <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="4">{{ __('debts.statement_owed_today') }}</td>
                            <td class="text-right">{{ number_format($owedToday, 2) }}</td>
                            <td></td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif
@endsection
