@extends('layouts.app')

@php
    $isSpot = $saleType === 'spot';
    $listRoute = $isSpot ? 'spot-sales.index' : 'ticket-sales.index';
@endphp
@section('title', __($isSpot ? 'menu.spot_sales' : 'menu.ticket_sales'))

@section('actions')
    @can('ticket_sales.manage')
        @if ($isSpot)
            <a href="{{ route('spot-sales.create') }}" class="btn btn-success"><i class="fas fa-gas-pump"></i> {{ __('sales.spot_sale') }}</a>
        @else
            <a href="{{ route('ticket-sales.create') }}" class="btn btn-success"><i class="fas fa-cash-register"></i> {{ __('sales.ticket_sale') }}</a>
        @endif
    @endcan
    @can('sales.delete')
        <a href="{{ route($listRoute, $deleted ? [] : ['deleted' => 1]) }}" class="btn btn-outline-secondary">
            @if ($deleted)
                <i class="fas fa-list"></i> {{ __('sale_delete.show_recent') }}
            @else
                <i class="fas fa-trash"></i> {{ __('sale_delete.show_deleted') }}
            @endif
        </a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->has('sale'))
        <div class="alert alert-danger">{{ $errors->first('sale') }}</div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ $deleted ? __('sale_delete.deleted_title') : __('sales.recent_sales') }}</h3></div>
        @if ($deleted)
            <div class="card-body pb-0 text-muted">{{ __('sale_delete.deleted_note') }}</div>
        @endif
        @if ($errors->has('sale_date') || $errors->has('customer'))
            <div class="card-body pb-0 text-danger">{{ $errors->first() }}</div>
        @endif
        {{-- every filter sits in the head of its own column; the inputs belong to this form by their form attribute --}}
        <form method="GET" action="{{ route($listRoute) }}" id="sale-filters">
            @if ($deleted)
                <input type="hidden" name="deleted" value="1">
            @endif
        </form>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap" id="sales-table">
                <thead>
                <tr>
                    <th>{{ __('sales.sale_date') }}</th>
                    <th>{{ __('sales.customer_id') }}</th>
                    <th>{{ __('sales.fuel_type') }}</th>
                    <th class="text-right">{{ __('sales.amount_kg') }}</th>
                    <th>{{ __('sales.payment_method') }}</th>
                    <th class="text-right">{{ __('sales.total_amount') }}</th>
                    <th class="text-right">{{ __('sales.paid_amount') }}</th>
                    <th class="text-right">{{ __('sales.unpaid_amount') }}</th>
                    <th>{{ __('sales.usage') }}</th>
                    @if ($deleted)
                        <th>{{ __('sale_delete.deleted_at') }}</th>
                    @endif
                    <th class="text-right">{{ __('sales.actions') }}</th>
                </tr>
                <tr class="filters">
                    <th><input name="sale_date" form="sale-filters" type="date" value="{{ $filters['sale_date'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('sale-filters').submit()"></th>
                    <th>
                        {{-- Select2 (AdminLTE's plugin): type to find a customer; picking one filters the list --}}
                        <select name="customer" form="sale-filters" id="sale-customer-filter" class="form-control form-control-sm" style="width: 100%; min-width: 200px">
                            <option value=""></option>
                            @foreach ($customers as $id => $name)
                                <option value="{{ $id }}" {{ (string) ($filters['customer'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th colspan="{{ $deleted ? 8 : 7 }}"></th>
                    <th class="text-right">
                        <button type="submit" form="sale-filters" class="btn btn-sm btn-primary">{{ __('sales.show') }}</button>
                        <a href="{{ route($listRoute, $deleted ? ['deleted' => 1] : []) }}" class="btn btn-sm btn-default">{{ __('sales.clear_filters') }}</a>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($sales as $s)
                    <tr>
                        <td>{{ $s->sale_date }}</td>
                        <td>{{ $customers[$s->customer_id] ?? $s->customer_id }}</td>
                        <td>{{ $fuels[$s->fuel_type] ?? $s->fuel_type }}</td>
                        <td class="text-right">{{ $s->amount_kg }}</td>
                        <td>{{ __('sales.payment_' . $s->payment_method) }}</td>
                        <td class="text-right">{{ number_format($s->total_amount, 2) }}</td>
                        <td class="text-right">{{ number_format($s->paid_amount, 2) }}</td>
                        <td class="text-right">{{ number_format($s->unpaid_amount, 2) }}</td>
                        <td>
                            {{ __('sales.usage_' . $s->usage) }}
                            @if ($s->isReturn())
                                <span class="badge badge-warning">{{ __('sale_return.badge') }}</span>
                            @elseif ($returnedKg->has($s->id))
                                <span class="badge badge-light">{{ __('sale_return.returned_kg', ['kg' => number_format($returnedKg[$s->id], 2)]) }}</span>
                            @endif
                        </td>
                        @if ($deleted)
                            <td>{{ $s->deleted_at }}</td>
                        @endif
                        <td class="text-nowrap text-right">
                            {{-- the actions of the sale in one drop-down --}}
                            <div class="btn-group sale-actions">
                                <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">{{ __('sales.actions') }}</button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a href="{{ route('ticket-sales.show', $s->id) }}" class="dropdown-item sale-detail" data-id="{{ $s->id }}"><i class="fas fa-eye fa-fw"></i> {{ __('sales.sale_view') }}</a>
                                    @if (!$deleted)
                                        @can('sales.edit')
                                            @if ($s->confirmed_at)
                                                <span class="dropdown-item text-muted" title="{{ __('sale_edit.locked') }}"><i class="fas fa-lock fa-fw"></i> {{ __('sales.edit') }}</span>
                                            @else
                                                <a href="{{ route('ticket-sales.edit', $s->id) }}" class="dropdown-item"><i class="fas fa-edit fa-fw"></i> {{ __('sales.edit') }}</a>
                                            @endif
                                        @endcan
                                    @endif
                                    @if (!$deleted && !$s->isReturn() && $returnable->has($s->id))
                                        <a href="{{ route('ticket-sales.return', $s->id) }}" class="dropdown-item"><i class="fas fa-undo fa-fw"></i> {{ __('sale_return.button') }}</a>
                                    @endif
                                    @if (!$deleted && $blockers->has($s->id))
                                        @if ($blockers[$s->id] === null)
                                            <form method="POST" action="{{ route('ticket-sales.destroy', $s->id) }}" onsubmit="return confirm(@json(__('sale_delete.confirm')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash fa-fw"></i> {{ __('sale_delete.delete') }}</button>
                                            </form>
                                        @else
                                            <span class="dropdown-item text-muted" title="{{ __('sale_delete.blocked_' . $blockers[$s->id]) }}"><i class="fas fa-lock fa-fw"></i> {{ __('sale_delete.delete') }}</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $deleted ? 11 : 10 }}" class="text-muted">{{ __('sales.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $sales->links() }}</div>
    </div>

    <div class="modal fade" id="sale-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('sales.sale_details') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="sale-modal-body"></div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    $(function () { // jQuery is loaded by the layout before this script
        $('#sale-customer-filter').select2({
            width: '100%', allowClear: true, placeholder: @json(__('sales.all_customers')), language: @json(app()->getLocale())
        }).on('change', function () { document.getElementById('sale-filters').submit(); });

        // The menu of the actions is fixed to the window (the table scrolls sideways and would cut it) and sits under its button.
        $(document).on('shown.bs.dropdown', '.sale-actions', function () {
            var button = $(this).find('.dropdown-toggle')[0].getBoundingClientRect();
            var menu = $(this).find('.dropdown-menu');
            menu.css({position: 'fixed', top: button.bottom + 'px', left: Math.max(4, button.right - menu.outerWidth()) + 'px', right: 'auto', transform: 'none'});
        });
        $(window).on('scroll resize', function () { $('.sale-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });
        $('.table-responsive').on('scroll', function () { $('.sale-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });

        $(document).on('click', 'a.sale-detail', function (e) {
            e.preventDefault();
            var body = $('#sale-modal-body');
            body.text('...');
            $('#sale-modal').modal('show');
            $.get($(this).attr('href'), {modal: 1})
                .done(function (html) { body.html(html); })
                .fail(function () { body.text(@json(__('sales.sale_not_found'))); });
        });
    });
    </script>
    @endpush
@endsection
