@extends('layouts.app')

@section('title', __('menu.supplements'))

@section('actions')
    @can('supplements.manage')
        <a href="{{ route('supplements.create') }}" class="btn btn-success"><i class="fas fa-gas-pump"></i> {{ __('supplements.record_supplement') }}</a>
    @endcan

    <div class="modal fade" id="supplement-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('supplements.supplement_details') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="supplement-modal-body"></div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    $(function () { // jQuery is loaded by the layout before this script
        // The menu of the actions is fixed to the window (the table may scroll sideways and would cut it) and sits under its button.
        $(document).on('shown.bs.dropdown', '.supplement-actions', function () {
            var button = $(this).find('.dropdown-toggle')[0].getBoundingClientRect();
            var menu = $(this).find('.dropdown-menu');
            menu.css({position: 'fixed', top: button.bottom + 'px', left: Math.max(4, button.right - menu.outerWidth()) + 'px', right: 'auto', transform: 'none'});
        });
        $(window).on('scroll resize', function () { $('.supplement-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });

        $(document).on('click', 'a.supplement-detail', function (e) {
            e.preventDefault();
            var body = $('#supplement-modal-body');
            body.text('...');
            $('#supplement-modal').modal('show');
            $.get($(this).attr('href'), {modal: 1})
                .done(function (html) { body.html(html); })
                .fail(function () { body.text(@json(__('supplements.supplement_not_found'))); });
        });
    });
    </script>
    @endpush
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-weight-hanging"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('supplements.total_dispensed_kg') }}</span>
                    <span class="info-box-number">{{ number_format($totalKg, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-box">
                <span class="info-box-icon bg-secondary"><i class="fas fa-tint"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('supplements.total_dispensed_liters') }}</span>
                    <span class="info-box-number">{{ number_format($totalLiters, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('menu.supplements') }}</h3></div>
        {{-- every filter sits in the head of its own column; the inputs belong to this form by their form attribute --}}
        <form method="GET" action="{{ route('supplements.index') }}" id="supplement-filters"></form>
        @if ($errors->any())
            <div class="card-body pb-0 text-danger">{{ $errors->first() }}</div>
        @endif
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('supplements.supply_date') }}</th>
                    <th>{{ __('supplements.nozzle_id') }}</th>
                    <th>{{ __('supplements.mode') }}</th>
                    <th class="text-right">{{ __('supplements.preset_kg') }}</th>
                    <th class="text-right">{{ __('supplements.density') }}</th>
                    <th class="text-right">{{ __('supplements.out_amount') }}</th>
                    <th>{{ __('supplements.remainder') }}</th>
                    <th class="text-right">{{ __('supplements.actions') }}</th>
                </tr>
                <tr class="filters">
                    <th><input name="supply_date" form="supplement-filters" type="date" value="{{ $filters['supply_date'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('supplement-filters').submit()"></th>
                    <th>
                        <select name="nozzle_id" form="supplement-filters" class="form-control form-control-sm" onchange="document.getElementById('supplement-filters').submit()">
                            <option value="">{{ __('supplements.all_nozzles') }}</option>
                            @foreach ($nozzleLabels as $id => $label)
                                <option value="{{ $id }}" {{ (string) ($filters['nozzle_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <select name="mode" form="supplement-filters" class="form-control form-control-sm" onchange="document.getElementById('supplement-filters').submit()">
                            <option value="">{{ __('supplements.all_modes') }}</option>
                            @foreach ($modes as $mode)
                                <option value="{{ $mode }}" {{ ($filters['mode'] ?? '') === $mode ? 'selected' : '' }}>{{ __('supplements.mode_' . $mode) }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th colspan="4"></th>
                    <th class="text-right">
                        <button type="submit" form="supplement-filters" class="btn btn-sm btn-primary">{{ __('supplements.show') }}</button>
                        <a href="{{ route('supplements.index') }}" class="btn btn-sm btn-default">{{ __('supplements.clear_filters') }}</a>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($supplements as $supplement)
                    <tr>
                        <td>{{ $supplement->supply_day }}</td>
                        <td>{{ $nozzleLabels[$supplement->nozzle_id] ?? $supplement->nozzle_id }}</td>
                        <td><span class="badge {{ $supplement->mode === 'manual' ? 'badge-warning' : 'badge-info' }}">{{ __('supplements.mode_' . $supplement->mode) }}</span></td>
                        <td class="text-right">{{ number_format($supplement->preset_kg, 2) }}</td>
                        <td class="text-right">{{ $supplement->density !== null ? rtrim(rtrim(number_format($supplement->density, 4, '.', ''), '0'), '.') : '-' }}</td>
                        <td class="text-right">{{ number_format($supplement->out_amount_kg, 2) }} {{ __('supplements.kg') }} / {{ number_format($supplement->out_amount_lt, 2) }} {{ __('supplements.liter') }}</td>
                        <td>
                            @if ($supplement->remainder)
                                @can('tickets.lookup')
                                    <a href="{{ route('tickets.lookup', ['barcode' => $supplement->remainder->ticket_barcode]) }}">{{ $supplement->remainder->ticket_barcode }}</a>
                                @else
                                    {{ $supplement->remainder->ticket_barcode }}
                                @endcan
                                ({{ number_format($supplement->remainder->amount_kg, 2) }} {{ __('supplements.kg') }})
                            @endif
                        </td>
                        <td class="text-nowrap text-right">
                            {{-- the actions of the supply in one drop-down --}}
                            <div class="btn-group supplement-actions">
                                <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">{{ __('supplements.actions') }}</button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a href="{{ route('supplements.show', $supplement->id) }}" class="dropdown-item supplement-detail"><i class="fas fa-eye fa-fw"></i> {{ __('supplements.sale_view') }}</a>
                                    @can('supplements.comment')
                                        @if (!$supplement->confirmed_at)
                                            <a href="{{ route('supplements.edit', $supplement->id) }}" class="dropdown-item"><i class="fas fa-pen fa-fw"></i> {{ __('supplements.edit') }}</a>
                                        @endif
                                    @endcan
                                    @can('supplements.manage')
                                        <form method="POST" action="{{ route('supplements.destroy', $supplement->id) }}" onsubmit="return confirm(@json(__('supplements.delete_confirm')))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash fa-fw"></i> {{ __('supplements.delete') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('supplements.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $supplements->links() }}</div>
    </div>

    <div class="modal fade" id="supplement-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('supplements.supplement_details') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="supplement-modal-body"></div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    $(function () { // jQuery is loaded by the layout before this script
        // The menu of the actions is fixed to the window (the table may scroll sideways and would cut it) and sits under its button.
        $(document).on('shown.bs.dropdown', '.supplement-actions', function () {
            var button = $(this).find('.dropdown-toggle')[0].getBoundingClientRect();
            var menu = $(this).find('.dropdown-menu');
            menu.css({position: 'fixed', top: button.bottom + 'px', left: Math.max(4, button.right - menu.outerWidth()) + 'px', right: 'auto', transform: 'none'});
        });
        $(window).on('scroll resize', function () { $('.supplement-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });

        $(document).on('click', 'a.supplement-detail', function (e) {
            e.preventDefault();
            var body = $('#supplement-modal-body');
            body.text('...');
            $('#supplement-modal').modal('show');
            $.get($(this).attr('href'), {modal: 1})
                .done(function (html) { body.html(html); })
                .fail(function () { body.text(@json(__('supplements.supplement_not_found'))); });
        });
    });
    </script>
    @endpush
@endsection
