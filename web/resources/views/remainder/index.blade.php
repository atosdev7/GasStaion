@extends('layouts.app')

@section('title', __('menu.remainders'))

@section('actions')
    @can('supplements.manage')
        <a href="{{ route('remainders.create') }}" class="btn btn-success"><i class="fas fa-plus"></i> {{ __('supplements.add_remainder') }}</a>
    @endcan
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
                    <span class="info-box-text">{{ __('supplements.remainders_total_kg') }}</span>
                    <span class="info-box-number">{{ number_format($totalKg, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('menu.remainders') }}</h3></div>
        {{-- every filter sits in the head of its own column; the inputs belong to this form by their form attribute --}}
        <form method="GET" action="{{ route('remainders.index') }}" id="remainder-filters"></form>
        @if ($errors->any())
            <div class="card-body pb-0 text-danger">{{ $errors->first() }}</div>
        @endif
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('supplements.remainder_date') }}</th>
                    <th>{{ __('supplements.barcode') }}</th>
                    <th>{{ __('supplements.fuel_type') }}</th>
                    <th class="text-right">{{ __('supplements.amount_kg') }}</th>
                    <th>{{ __('supplements.remainder_of_supply') }}</th>
                    <th>{{ __('supplements.complete_date') }}</th>
                    <th>{{ __('supplements.comment') }}</th>
                    <th>{{ __('supplements.status') }}</th>
                    <th class="text-right">{{ __('supplements.actions') }}</th>
                </tr>
                <tr class="filters">
                    <th><input name="date" form="remainder-filters" type="date" value="{{ $filters['date'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('remainder-filters').submit()"></th>
                    <th><input name="q" form="remainder-filters" type="text" maxlength="16" placeholder="{{ __('supplements.barcode_contains') }}" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" style="min-width: 190px"></th>
                    <th>
                        <select name="fuel_type" form="remainder-filters" class="form-control form-control-sm" onchange="document.getElementById('remainder-filters').submit()">
                            <option value="">{{ __('supplements.all_fuel_types') }}</option>
                            @foreach ($fuels as $key => $name)
                                <option value="{{ $key }}" {{ ($filters['fuel_type'] ?? '') === (string) $key ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th colspan="5"></th>
                    <th class="text-right">
                        <button type="submit" form="remainder-filters" class="btn btn-sm btn-primary">{{ __('supplements.show') }}</button>
                        <a href="{{ route('remainders.index') }}" class="btn btn-sm btn-default">{{ __('supplements.clear_filters') }}</a>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($remainders as $r)
                    @php($supply = $supplements->get($r->supplement_id))
                    <tr>
                        <td>{{ $r->remainder_date ? substr((string) $r->remainder_date, 0, 10) : substr((string) $r->created_at, 0, 10) }}</td>
                        <td>@can('tickets.lookup')<a href="{{ route('tickets.lookup', ['barcode' => $r->ticket_barcode]) }}">{{ $r->ticket_barcode }}</a>@else{{ $r->ticket_barcode }}@endcan</td>
                        <td>{{ $fuels[$r->fuel_type] ?? $r->fuel_type }}</td>
                        <td class="text-right">{{ number_format($r->amount_kg, 2) }}</td>
                        <td>
                            @if ($supply)
                                <a href="{{ route('supplements.show', $supply->id) }}">{{ $supply->supply_day }}</a>
                                &middot; {{ $nozzleLabels[$supply->nozzle_id] ?? $supply->nozzle_id }}
                            @elseif ($r->supplement_id === null)
                                <span class="text-muted">{{ __('supplements.remainder_opening') }}</span>
                            @else
                                <span class="text-muted">{{ __('supplements.remainder_supply_cleared') }}</span>
                            @endif
                        </td>
                        <td>{{ $r->complete_date ? substr((string) $r->complete_date, 0, 10) : '-' }}</td>
                        <td>{{ $r->comment }}</td>
                        <td>
                            @if ($states->has($r->ticket_barcode))
                                <span class="badge {{ (int) $states[$r->ticket_barcode] === config('ticket_statuses.restrict') ? 'badge-danger' : 'badge-info' }}">{{ __('supplements.ticket_status_' . array_search((int) $states[$r->ticket_barcode], config('ticket_statuses'))) }}</span>
                            @endif
                        </td>
                        <td class="text-nowrap text-right">
                            {{-- the actions of the remainder in one drop-down: the supply it came from (a popup), its ticket, its comment --}}
                            <div class="btn-group remainder-actions">
                                <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">{{ __('supplements.actions') }}</button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    @if ($supply)
                                        <a href="{{ route('supplements.show', $supply->id) }}" class="dropdown-item supplement-detail"><i class="fas fa-eye fa-fw"></i> {{ __('supplements.view_supply') }}</a>
                                    @endif
                                    @can('tickets.lookup')
                                        <a href="{{ route('tickets.lookup', ['barcode' => $r->ticket_barcode]) }}" class="dropdown-item"><i class="fas fa-ticket-alt fa-fw"></i> {{ __('supplements.ticket_lookup') }}</a>
                                    @endcan
                                    @can('supplements.comment')
                                        <a href="#" class="dropdown-item remainder-comment" data-action="{{ route('remainders.comment', $r->id) }}" data-comment="{{ $r->comment }}" data-barcode="{{ $r->ticket_barcode }}"><i class="fas fa-pen fa-fw"></i> {{ __('supplements.edit_comment') }}</a>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">{{ __('supplements.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $remainders->links() }}</div>
    </div>

    @can('supplements.comment')
        <div class="modal fade" id="remainder-comment-modal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="" id="remainder-comment-form" class="modal-content">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('supplements.edit_comment') }} <small class="text-muted" id="remainder-comment-barcode"></small></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label for="remainder-comment">{{ __('supplements.comment') }}</label>
                            <input type="text" id="remainder-comment" name="comment" maxlength="255" autocomplete="off" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('supplements.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('supplements.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
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
@endsection

@push('scripts')
    <script>
    $(function () { // jQuery is loaded by the layout before this script
        // The menu of the actions is fixed to the window (the table may scroll sideways and would cut it) and sits under its button.
        $(document).on('shown.bs.dropdown', '.remainder-actions', function () {
            var button = $(this).find('.dropdown-toggle')[0].getBoundingClientRect();
            var menu = $(this).find('.dropdown-menu');
            menu.css({position: 'fixed', top: button.bottom + 'px', left: Math.max(4, button.right - menu.outerWidth()) + 'px', right: 'auto', transform: 'none'});
        });
        $(window).on('scroll resize', function () { $('.remainder-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });
        $('.table-responsive').on('scroll', function () { $('.remainder-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });

        $(document).on('click', 'a.remainder-comment', function (e) {
            e.preventDefault();
            $('#remainder-comment-form').attr('action', $(this).data('action'));
            $('#remainder-comment-barcode').text($(this).data('barcode'));
            $('#remainder-comment').val($(this).attr('data-comment'));
            $('#remainder-comment-modal').modal('show');
        });
        $('#remainder-comment-modal').on('shown.bs.modal', function () { $('#remainder-comment').trigger('focus'); });

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