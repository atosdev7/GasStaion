@extends('layouts.app')

@section('title', __('menu.group_confirmations') . ' - ' . __('menu.confirmation_sales'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @include('confirmation._header', ['route' => 'confirmations.index', 'area' => 'sales'])

    @if ($deletedSales)
        <div class="alert alert-warning">{{ __('confirm.will_erase', ['count' => $deletedSales]) }}</div>
    @endif

    {{-- Ticket sales / Spot sales / Others, each tab its own form with its own confirm button and its own table, exported to Excel as one sheet.
         The spot table has the first fuel type (gasoline) on the left and the second (diesel) on the right, in the same <table>; ticket and others
         are one table with a fuel column. --}}
    <div class="card card-outline card-primary card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
                @foreach ($groups as $block => $group)
                    <li class="nav-item"><a class="nav-link {{ $loop->first ? 'active' : '' }}" id="tab-{{ $block }}-link" data-toggle="pill" href="#tab-{{ $block }}" role="tab">{{ $block === 'others' ? __('confirm.tab_others') : __('menu.' . $block . '_sales') }} ({{ $group['count'] }})</a></li>
                @endforeach
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                @foreach ($groups as $block => $group)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab-{{ $block }}" role="tabpanel">
                        <form method="POST" action="{{ route('confirmations.store') }}">
                            @csrf
                            <input type="hidden" name="date" value="{{ $date }}">
                            <input type="hidden" name="from" value="sales">
                            <input type="hidden" name="tab" value="{{ $block }}">

                            {{-- a tab with the fuel types apart (the spot sales) shows one table for each, one under the other; the Excel button stands at the right of the
                                 heading of the first --}}
                            @foreach (count($group['sides']) > 1 ? $group['sides'] : [null] as $part)
                                @if ($part)
                                    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                                        <h6 class="font-weight-bold mb-0">{{ $part['title'] }}</h6>
                                        @if ($loop->first)
                                            <a href="{{ route('confirmations.sales-export', ['date' => $date, 'block' => $block]) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel"></i> {{ __('confirm.export_xls') }}</a>
                                        @endif
                                    </div>
                                @endif
                                @include('confirmation._sales_table', [
                                    'sides' => $part ? [$part] : $group['sides'],
                                    'export' => route('confirmations.sales-export', ['date' => $date, 'block' => $block]),
                                    'showExport' => !$part,
                                ])
                            @endforeach

                            @if ($group['rows']->contains(function ($sale) { return !$sale->confirmed_at; }) || $deletedSales)
                                <button type="submit" class="btn btn-success" onclick="return confirm(@json(__('confirm.confirm_question', ['date' => $date])))">
                                    <i class="fas fa-check-double"></i> {{ __('confirm.confirm') }}
                                </button>
                                <small class="text-muted ml-2">{{ __('confirm.confirm_hint') }}</small>
                            @endif
                        </form>
                    </div>
                @endforeach
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
        // the tab stays in the address, so that the page comes back on it after confirming
        if (location.hash && $('.nav-tabs a[href="' + location.hash + '"]').length) {
            $('.nav-tabs a[href="' + location.hash + '"]').tab('show');
        }
        $('.nav-tabs a[data-toggle="pill"]').on('shown.bs.tab', function () {
            history.replaceState(null, '', location.pathname + location.search + $(this).attr('href'));
        });
    </script>
@endpush
