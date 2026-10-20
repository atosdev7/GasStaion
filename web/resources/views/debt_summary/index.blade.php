@extends('layouts.app')

@section('title', __('menu.group_debts') . ' - ' . __('menu.debt_summary'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('debt-summary.index') }}" class="form-inline">
                <label for="f_from" class="mr-2">{{ __('debts.from') }}</label>
                <input id="f_from" name="from" type="date" value="{{ $from }}" class="form-control mr-3">
                <label for="f_to" class="mr-2">{{ __('debts.to') }}</label>
                <input id="f_to" name="to" type="date" value="{{ $to }}" class="form-control mr-3">
                <button type="submit" class="btn btn-primary">{{ __('debts.show') }}</button>
                <a href="{{ route('debt-summary.export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success ml-2"><i class="fas fa-file-excel"></i> {{ __('debts.export_xls') }}</a>
            </form>
            @if ($errors->any())
                <div class="text-danger mt-2">{{ $errors->first() }}</div>
            @endif
            <small class="form-text text-muted">{{ __('debt_summary.hint') }}</small>
        </div>
    </div>

    <div class="card card-outline card-primary card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" id="tab-payments-link" data-toggle="pill" href="#tab-payments" role="tab">{{ __('menu.payments') }} ({{ count($payments) }})</a></li>
                @if ($loans !== null)
                    <li class="nav-item"><a class="nav-link" id="tab-loans-link" data-toggle="pill" href="#tab-loans" role="tab">{{ __('menu.ticket_loans') }} ({{ count($loans) }})</a></li>
                @endif
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                {{-- Payments: the money owed, by customer; each figure in bank and in cash --}}
                <div class="tab-pane fade show active" id="tab-payments" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover text-nowrap">
                            <thead>
                            <tr>
                                <th rowspan="2">{{ __('debts.customer_id') }}</th>
                                @foreach (['opening', 'debts', 'payments', 'closing'] as $group)
                                    <th colspan="2" class="text-center border-left">{{ __('debt_summary.' . $group) }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach (['opening', 'debts', 'payments', 'closing'] as $group)
                                    <th class="text-right border-left">{{ __('debts.payment_bank') }}</th>
                                    <th class="text-right">{{ __('debts.payment_cash') }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($payments as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    @foreach (['opening', 'debts', 'payments', 'closing'] as $group)
                                        <td class="text-right border-left">{{ number_format($row[$group]['bank'], 2) }}</td>
                                        <td class="text-right">{{ number_format($row[$group]['cash'], 2) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-muted">{{ __('debts.no_data') }}</td></tr>
                            @endforelse
                            </tbody>
                            @if (count($payments))
                                <tfoot>
                                @php($all = collect($payments))
                                <tr class="font-weight-bold">
                                    <td>{{ __('debts.total') }}</td>
                                    @foreach (['opening', 'debts', 'payments', 'closing'] as $group)
                                        <td class="text-right border-left">{{ number_format($all->sum($group . '.bank'), 2) }}</td>
                                        <td class="text-right">{{ number_format($all->sum($group . '.cash'), 2) }}</td>
                                    @endforeach
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- Ticket loans: the tickets borrowed in kg, by customer, the fuel types side by side --}}
                @if ($loans !== null)
                    <div class="tab-pane fade" id="tab-loans" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover text-nowrap">
                                <thead>
                                <tr>
                                    <th rowspan="2">{{ __('debts.customer_id') }}</th>
                                    @foreach ($fuelKeys as $fuel)
                                        <th colspan="4" class="text-center border-left">{{ $fuels[$fuel] ?? $fuel }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach ($fuelKeys as $fuel)
                                        <th class="text-right border-left">{{ __('debt_summary.opening') }}</th>
                                        <th class="text-right">{{ __('debt_summary.loaned') }}</th>
                                        <th class="text-right">{{ __('debt_summary.settled') }}</th>
                                        <th class="text-right">{{ __('debt_summary.closing') }}</th>
                                    @endforeach
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($loans as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        @foreach ($fuelKeys as $fuel)
                                            @php($line = $row['fuels'][$fuel] ?? ['opening' => 0, 'loaned' => 0, 'settled' => 0, 'closing' => 0])
                                            <td class="text-right border-left">{{ number_format($line['opening'], 2) }}</td>
                                            <td class="text-right">{{ number_format($line['loaned'], 2) }}</td>
                                            <td class="text-right">{{ number_format($line['settled'], 2) }}</td>
                                            <td class="text-right">{{ number_format($line['closing'], 2) }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ 1 + 4 * count($fuelKeys) }}" class="text-muted">{{ __('debts.no_data') }}</td></tr>
                                @endforelse
                                </tbody>
                                @if (count($loans))
                                    <tfoot>
                                    <tr class="font-weight-bold">
                                        <td>{{ __('debts.total') }}</td>
                                        @foreach ($fuelKeys as $fuel)
                                            @php($column = collect($loans)->pluck('fuels.' . $fuel))
                                            <td class="text-right border-left">{{ number_format($column->sum('opening'), 2) }}</td>
                                            <td class="text-right">{{ number_format($column->sum('loaned'), 2) }}</td>
                                            <td class="text-right">{{ number_format($column->sum('settled'), 2) }}</td>
                                            <td class="text-right">{{ number_format($column->sum('closing'), 2) }}</td>
                                        @endforeach
                                    </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // the tab stays in the address
        if (location.hash && $('.nav-tabs a[href="' + location.hash + '"]').length) {
            $('.nav-tabs a[href="' + location.hash + '"]').tab('show');
        }
        $('.nav-tabs a[data-toggle="pill"]').on('shown.bs.tab', function () {
            history.replaceState(null, '', location.pathname + location.search + $(this).attr('href'));
        });
    </script>
@endpush
