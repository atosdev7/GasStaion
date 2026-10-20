@extends('layouts.app')

@section('title', __('menu.ticket_lookup'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @error('barcode')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('tickets.tickets_table') }}</h3>
            {{-- how many tickets are in each state under the filters, at the right end of the title bar --}}
            <div class="card-tools" id="ticket-counts">
                @foreach (config('ticket_statuses') as $name => $value)
                    <span class="badge badge-secondary ml-1">{{ __('tickets.ticket_status_' . $name) }}: {{ $perStatus[$value] ?? 0 }}</span>
                @endforeach
            </div>
        </div>
        @if ($errors->any() && !$errors->has('barcode'))
            <div class="card-body pb-0 text-danger">{{ $errors->first() }}</div>
        @endif
        {{-- every filter sits in the head of its own column; the inputs belong to this form by their form attribute --}}
        <form method="GET" action="{{ route('tickets.lookup') }}" id="ticket-filters"></form>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap" id="tickets-table" style="table-layout: fixed; min-width: 1240px">
                <colgroup>
                    <col style="width: 200px">  {{-- barcode --}}
                    <col style="width: 120px">  {{-- fuel type --}}
                    <col style="width: 130px">  {{-- spec --}}
                    <col style="width: 120px">  {{-- state --}}
                    <col style="width: 150px">  {{-- published --}}
                    <col style="width: 160px">  {{-- sold at --}}
                    <col style="width: 160px">  {{-- supplied at --}}
                    <col style="width: 200px">  {{-- actions --}}
                </colgroup>
                <thead>
                <tr style="white-space: normal">
                    <th>{{ __('tickets.barcode') }}</th>
                    <th>{{ __('tickets.fuel_type') }}</th>
                    <th class="text-right">{{ __('tickets.spec') }}</th>
                    <th>{{ __('tickets.status') }}</th>
                    <th>{{ __('tickets.ticket_publish_date') }}</th>
                    <th>{{ __('tickets.sold_at') }}</th>
                    <th>{{ __('tickets.supplied_at') }}</th>
                    <th class="text-right">{{ __('tickets.actions') }}</th>
                </tr>
                <tr class="filters">
                    <th><input name="q" form="ticket-filters" type="text" maxlength="16" placeholder="{{ __('tickets.barcode_contains') }}" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" style="min-width: 190px"></th>
                    <th>
                        <select name="fuel_type" form="ticket-filters" class="form-control form-control-sm" onchange="document.getElementById('ticket-filters').submit()">
                            <option value="">{{ __('tickets.all_fuel_types') }}</option>
                            @foreach ($fuels as $key => $name)
                                <option value="{{ $key }}" {{ ($filters['fuel_type'] ?? '') === (string) $key ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <select name="spec" form="ticket-filters" class="form-control form-control-sm" onchange="document.getElementById('ticket-filters').submit()">
                            <option value="">{{ __('tickets.all_specs') }}</option>
                            @foreach ($specs as $key => $kg)
                                <option value="{{ $key }}" {{ (string) ($filters['spec'] ?? '') === (string) $key ? 'selected' : '' }}>{{ $kg }} {{ __('tickets.kg') }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <select name="status" form="ticket-filters" class="form-control form-control-sm" onchange="document.getElementById('ticket-filters').submit()">
                            <option value="">{{ __('tickets.all_statuses') }}</option>
                            @foreach (config('ticket_statuses') as $name => $value)
                                <option value="{{ $value }}" {{ (string) ($filters['status'] ?? '') === (string) $value ? 'selected' : '' }}>{{ __('tickets.ticket_status_' . $name) }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th><input name="published" form="ticket-filters" type="date" value="{{ $filters['published'] ?? '' }}" class="form-control form-control-sm" onchange="document.getElementById('ticket-filters').submit()"></th>
                    <th><input name="sold" form="ticket-filters" type="date" value="{{ $filters['sold'] ?? '' }}" class="form-control form-control-sm" onchange="document.getElementById('ticket-filters').submit()"></th>
                    <th><input name="supplied" form="ticket-filters" type="date" value="{{ $filters['supplied'] ?? '' }}" class="form-control form-control-sm" onchange="document.getElementById('ticket-filters').submit()"></th>
                    <th class="text-right">
                        <button type="submit" form="ticket-filters" class="btn btn-sm btn-primary">{{ __('tickets.show') }}</button>
                        <a href="{{ route('tickets.lookup') }}" class="btn btn-sm btn-default">{{ __('tickets.clear_filters') }}</a>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($tickets as $t)
                    <tr>
                        <td><a href="{{ route('tickets.lookup', array_merge(request()->query(), ['barcode' => $t->barcode])) }}" class="ticket-detail" data-barcode="{{ $t->barcode }}">{{ $t->barcode }}</a></td>
                        <td>{{ $fuels[$t->fuel_type] ?? $t->fuel_type }}</td>
                        <td class="text-right">{{ !is_null($t->spec) ? config('ticket_specs.' . $t->spec) . ' ' . __('tickets.kg') : number_format($t->amount_kg, 2) . ' ' . __('tickets.kg') }}</td>
                        <td><span class="badge {{ (int) $t->status === config('ticket_statuses.restrict') ? 'badge-danger' : 'badge-info' }}">{{ __('tickets.ticket_status_' . array_search((int) $t->status, config('ticket_statuses'))) }}</span></td>
                        <td>{{ $t->batch && $t->batch->date ? substr((string) $t->batch->date, 0, 10) : substr((string) $t->publish_date, 0, 10) }}</td>
                        <td>{{ substr((string) $t->sale_date, 0, 10) }}</td>
                        <td>{{ substr((string) $t->supply_date, 0, 10) }}</td>
                        <td class="text-right">
                            <a href="{{ route('tickets.lookup', array_merge(request()->query(), ['barcode' => $t->barcode])) }}" class="btn btn-sm btn-outline-secondary ticket-detail" data-barcode="{{ $t->barcode }}">{{ __('tickets.ticket_details') }}</a>
                            @can('tickets.restrict')
                                @if (in_array((int) $t->status, [config('ticket_statuses.publish'), config('ticket_statuses.sale')], true))
                                    <form method="POST" action="{{ route('tickets.restrict', $t->barcode) }}" class="d-inline"
                                          onsubmit="return confirm(@json(__('tickets.restrict_confirm')))">
                                        @csrf
                                        <input type="hidden" name="back" value="{{ request()->getQueryString() }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('tickets.ticket_restrict') }}</button>
                                    </form>
                                @elseif ((int) $t->status === config('ticket_statuses.restrict'))
                                    <form method="POST" action="{{ route('tickets.lift-restriction', $t->barcode) }}" class="d-inline"
                                          onsubmit="return confirm(@json(__('tickets.lift_confirm')))">
                                        @csrf
                                        <input type="hidden" name="back" value="{{ request()->getQueryString() }}">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">{{ __('tickets.ticket_lift') }}</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('tickets.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $tickets->links() }}</div>
    </div>

    <div class="modal fade" id="ticket-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('menu.ticket_lookup') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="ticket-modal-body"></div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    $(function () { // jQuery is loaded by the layout before this script
        var url = @json(route('tickets.lookup'));
        var openBarcode = @json($openBarcode);

        function openTicket(barcode) {
            var body = $('#ticket-modal-body');
            body.text('...');
            $('#ticket-modal').modal('show');
            $.get(url, {barcode: barcode, modal: 1, back: window.location.search.replace(/^\?/, '').replace(/(^|&)(barcode|page)=[^&]*/g, '').replace(/^&/, '')})
                .done(function (html) { body.html(html); })
                .fail(function () { body.text(@json(__('tickets.ticket_not_found'))); });
        }

        $(document).on('click', 'a.ticket-detail', function (e) {
            e.preventDefault();
            openTicket($(this).data('barcode'));
        });

        if (openBarcode) {
            openTicket(openBarcode);
        }
    });
    </script>
    @endpush
@endsection
