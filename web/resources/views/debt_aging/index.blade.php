@extends('layouts.app')

@section('title', __('menu.group_debts') . ' - ' . __('menu.debt_aging'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('debt-aging.index') }}" class="form-inline">
                <label for="f_customer" class="mr-2">{{ __('debts.customer_id') }}</label>
                <select id="f_customer" name="customer" class="form-control js-select2 mr-3" data-allow-clear style="min-width: 220px">
                    <option value="">{{ __('debts.all_customers') }}</option>
                    @foreach ($customers as $id => $name)
                        <option value="{{ $id }}" {{ (string) ($filters['customer'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                <label for="f_method" class="mr-2">{{ __('debts.payment_method') }}</label>
                <select id="f_method" name="payment_method" class="form-control mr-3">
                    <option value="">{{ __('debts.all_payment_methods') }}</option>
                    @foreach (['cash', 'bank'] as $method)
                        <option value="{{ $method }}" {{ ($filters['payment_method'] ?? '') === $method ? 'selected' : '' }}>{{ __('debts.payment_' . $method) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">{{ __('debts.show') }}</button>
            </form>
            @if ($errors->any())
                <div class="text-danger mt-2">{{ $errors->first() }}</div>
            @endif
            <small class="form-text text-muted">{{ __('debts.aging_hint') }} ({{ $today }})</small>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap" id="aging-table">
                <thead>
                <tr>
                    <th>{{ __('debts.aging_customer') }}</th>
                    @foreach ($buckets as $bucket)
                        <th class="text-right">{{ $bucket[1] === null ? __('debts.aging_over', ['from' => $bucket[0] - 1]) : __('debts.aging_days', ['from' => $bucket[0], 'to' => $bucket[1]]) }}</th>
                    @endforeach
                    <th class="text-right">{{ __('debts.aging_total') }}</th>
                    <th class="text-right">{{ __('debts.aging_oldest') }}</th>
                    <th class="text-right">{{ __('debts.aging_credit') }}</th>
                    <th class="text-right"></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr class="aging-row">
                        <td>{{ $row['name'] }}</td>
                        @foreach ($row['buckets'] as $amount)
                            <td class="text-right {{ $amount > 0 ? '' : 'text-muted' }}">{{ number_format($amount, 2) }}</td>
                        @endforeach
                        <td class="text-right font-weight-bold">{{ number_format($row['total'], 2) }}</td>
                        <td class="text-right">{{ $row['oldest'] }}</td>
                        <td class="text-right {{ $row['credit'] > 0 ? 'text-info' : 'text-muted' }}">{{ number_format($row['credit'], 2) }}</td>
                        <td class="text-right"><a href="{{ route('debt-statement.show', ['customer' => $row['customer_id']]) }}" class="btn btn-xs btn-outline-primary">{{ __('debts.aging_statement') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($buckets) + 5 }}" class="text-muted">{{ __('debts.aging_nothing') }}</td></tr>
                @endforelse
                </tbody>
                @if ($rows)
                    <tfoot>
                    <tr class="font-weight-bold">
                        <td>{{ __('debts.total_balance') }}</td>
                        @foreach ($totals['buckets'] as $amount)
                            <td class="text-right">{{ number_format($amount, 2) }}</td>
                        @endforeach
                        <td class="text-right">{{ number_format($totals['total'], 2) }}</td>
                        <td></td>
                        <td class="text-right">{{ number_format($totals['credit'], 2) }}</td>
                        <td></td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
