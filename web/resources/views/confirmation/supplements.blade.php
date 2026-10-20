@extends('layouts.app')

@section('title', __('menu.group_confirmations') . ' - ' . __('menu.confirmation_supplements'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @include('confirmation._header', ['route' => 'confirmations.supplements'])

    {{-- Supplements (to confirm; one table, the first fuel type on the left, the second on the right) / Remainders (the remainders of the date) --}}
    <div class="card card-outline card-primary card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" id="tab-supplements-link" data-toggle="pill" href="#tab-supplements" role="tab">{{ __('confirm.supplements') }} ({{ $supplements->count() }})</a></li>
                <li class="nav-item"><a class="nav-link" id="tab-remainders-link" data-toggle="pill" href="#tab-remainders" role="tab">{{ __('menu.remainders') }} ({{ $remainders->count() }})</a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-supplements" role="tabpanel">
                    <form method="POST" action="{{ route('confirmations.store') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <input type="hidden" name="from" value="supplements">

                        <div class="clearfix mb-2">
                            <a href="{{ route('confirmations.supplements-export', ['date' => $date]) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel"></i> {{ __('confirm.export_xls') }}</a>
                        </div>
                        @include('confirmation._supplements_table')

                        @if ($supplements->contains(function ($supplement) { return !$supplement->confirmed_at; }))
                            <button type="submit" class="btn btn-success" onclick="return confirm(@json(__('confirm.confirm_question', ['date' => $date])))">
                                <i class="fas fa-check-double"></i> {{ __('confirm.confirm') }}
                            </button>
                            <small class="text-muted ml-2">{{ __('confirm.confirm_hint') }}</small>
                        @endif
                    </form>
                </div>

                <div class="tab-pane fade" id="tab-remainders" role="tabpanel">
                    <div class="clearfix mb-2">
                        <a href="{{ route('confirmations.remainders-export', ['date' => $date]) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel"></i> {{ __('confirm.export_xls') }}</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover text-nowrap">
                            <thead>
                            <tr>
                                <th>{{ __('confirm.barcode') }}</th>
                                <th>{{ __('confirm.fuel_type') }}</th>
                                <th class="text-right">{{ __('confirm.amount_kg') }}</th>
                                <th>{{ __('confirm.remainder_of_supply') }}</th>
                                <th>{{ __('confirm.complete_date') }}</th>
                                <th>{{ __('confirm.comment') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($remainders as $remainder)
                                <tr>
                                    <td>{{ $remainder->ticket_barcode }}</td>
                                    <td>{{ $fuels[$remainder->fuel_type] ?? $remainder->fuel_type }}</td>
                                    <td class="text-right">{{ number_format($remainder->amount_kg, 2) }}</td>
                                    <td>
                                        @if ($remainder->supplement)
                                            {{ $remainder->supplement->supplied_at }} &middot; {{ $nozzleLabels[$remainder->supplement->nozzle_id] ?? $remainder->supplement->nozzle_id }}
                                        @endif
                                    </td>
                                    <td>{{ $remainder->complete_date ? substr((string) $remainder->complete_date, 0, 10) : '-' }}</td>
                                    <td>{{ $remainder->comment }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">{{ __('confirm.no_data') }}</td></tr>
                            @endforelse
                            </tbody>
                            @if ($remainders->isNotEmpty())
                                <tfoot>
                                <tr class="font-weight-bold">
                                    <td colspan="2">{{ __('confirm.total') }}</td>
                                    <td class="text-right">{{ number_format($remainders->sum('amount_kg'), 2) }}</td>
                                    <td colspan="3"></td>
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@include('confirmation._record_modal')

@push('scripts')
    <script>
        $('.check-all').on('change', function () {
            $('.' + $(this).data('target')).prop('checked', this.checked);
        });
        // the tab stays in the address
        if (location.hash && $('.nav-tabs a[href="' + location.hash + '"]').length) {
            $('.nav-tabs a[href="' + location.hash + '"]').tab('show');
        }
        $('.nav-tabs a[data-toggle="pill"]').on('shown.bs.tab', function () {
            history.replaceState(null, '', location.pathname + location.search + $(this).attr('href'));
        });
    </script>
@endpush
