@extends('layouts.app')

@section('title', __('menu.group_confirmations') . ' - ' . __('menu.confirmation_debts'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @include('confirmation._header', ['route' => 'confirmations.debts'])

    {{-- The debts of the date: the debt payments received (a reversal is a line of its own, with the negative amount) and the settlements of the ticket loans (the tickets
         given back; a reversal has the negative kg). The debts and the ticket loans themselves are not confirmed. The confirm button confirms the ones checked that
         are not confirmed yet; Unconfirm (a manager) sends the same form to its own address (formaction). --}}
    <form method="POST" action="{{ route('confirmations.store') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="from" value="debts">
        <p class="text-muted">{{ __('confirm.payments_hint') }}</p>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('confirm.debt_payments') }} ({{ $payments->count() }})</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th style="width: 30px"><input type="checkbox" class="check-all" data-target="check-debt-payments" title="{{ __('confirm.check_all') }}"></th>
                        <th>#</th>
                        <th>{{ __('confirm.customer_id') }}</th>
                        <th>{{ __('confirm.debt') }}</th>
                        <th>{{ __('confirm.payment_method') }}</th>
                        <th class="text-right">{{ __('confirm.amount') }}</th>
                        <th>{{ __('confirm.note') }}</th>
                        <th class="text-right">{{ __('confirm.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($payments as $row)
                        <tr>
                            <td>
                                @if ($row->isConfirmed())
                                    <i class="fas fa-check-circle text-success" title="{{ __('confirm.confirmed') }}"></i><span class="sr-only">{{ __('confirm.confirmed') }}</span>
                                @else
                                    <input type="checkbox" name="debt_payments[]" value="{{ $row->id }}" class="check-debt-payments">
                                @endif
                            </td>
                            <td>{{ $row->id }}</td>
                            <td>{{ $customers[$row->customer_id] ?? $row->customer_id }}</td>
                            <td>{{ $row->debt && $row->debt->sale ? $row->debt->sale->sale_date : __('confirm.debt_unallocated') }}</td>
                            <td>{{ __('confirm.payment_' . $row->payment_method) }}</td>
                            <td class="text-right">{{ number_format($row->amount, 2) }}</td>
                            <td>{{ $row->note }}</td>
                            <td class="text-right">
                                @if ($row->isConfirmed())
                                    @can('confirmations.unconfirm')
                                        <button type="submit" formmethod="post" formaction="{{ route('payments.unconfirm', $row->id) }}" class="btn btn-xs btn-outline-secondary"
                                                onclick="return confirm(@json(__('confirm.unconfirm_confirm')))"><i class="fas fa-undo fa-fw"></i> {{ __('confirm.unconfirm') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">{{ __('confirm.nothing_here') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if ($payments->isNotEmpty())
                        <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="5">{{ __('confirm.total') }}</td>
                            <td class="text-right">{{ number_format($payments->sum('amount'), 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('confirm.ticket_settlements') }} ({{ $settlements->count() }})</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th style="width: 30px"><input type="checkbox" class="check-all" data-target="check-ticket-settlements" title="{{ __('confirm.check_all') }}"></th>
                        <th>{{ __('confirm.settled_at') }}</th>
                        <th>{{ __('confirm.customer_id') }}</th>
                        <th>{{ __('confirm.fuel_type') }}</th>
                        <th>{{ __('confirm.ticket') }}</th>
                        <th class="text-right">{{ __('confirm.amount_kg') }}</th>
                        <th>{{ __('confirm.note') }}</th>
                        <th class="text-right">{{ __('confirm.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($settlements as $row)
                        <tr>
                            <td>
                                @if ($row->isConfirmed())
                                    <i class="fas fa-check-circle text-success" title="{{ __('confirm.confirmed') }}"></i><span class="sr-only">{{ __('confirm.confirmed') }}</span>
                                @else
                                    <input type="checkbox" name="ticket_settlements[]" value="{{ $row->id }}" class="check-ticket-settlements">
                                @endif
                            </td>
                            <td>{{ substr((string) $row->settled_at, 11, 5) }}</td>
                            <td>{{ $row->loan ? ($customers[$row->loan->customer_id] ?? $row->loan->customer_id) : '' }}</td>
                            <td>{{ $row->loan ? ($fuels[$row->loan->fuel_type] ?? $row->loan->fuel_type) : '' }}</td>
                            <td>{{ $row->ticket_barcode }}</td>
                            <td class="text-right">{{ number_format($row->amount_kg, 2) }}</td>
                            <td>{{ $row->comment }}</td>
                            <td class="text-right">
                                @if ($row->isConfirmed())
                                    @can('confirmations.unconfirm')
                                        <button type="submit" formmethod="post" formaction="{{ route('loans.settlements.unconfirm', $row->id) }}" class="btn btn-xs btn-outline-secondary"
                                                onclick="return confirm(@json(__('confirm.unconfirm_confirm')))"><i class="fas fa-undo fa-fw"></i> {{ __('confirm.unconfirm') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">{{ __('confirm.nothing_here') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if ($settlements->isNotEmpty())
                        <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="5">{{ __('confirm.total') }}</td>
                            <td class="text-right">{{ number_format($settlements->sum('amount_kg'), 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        @if ($payments->contains(function ($row) { return !$row->isConfirmed(); }) || $settlements->contains(function ($row) { return !$row->isConfirmed(); }))
            <button type="submit" class="btn btn-success" onclick="return confirm(@json(__('confirm.confirm_question', ['date' => $date])))">
                <i class="fas fa-check-double"></i> {{ __('confirm.confirm') }}
            </button>
            <small class="text-muted ml-2">{{ __('confirm.confirm_hint') }}</small>
        @endif
    </form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.check-all').forEach(function (box) {
    box.addEventListener('change', function () {
        document.querySelectorAll('.' + box.getAttribute('data-target')).forEach(function (el) { el.checked = box.checked; });
    });
});
</script>
@endpush
