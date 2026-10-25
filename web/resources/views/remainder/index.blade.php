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
                    <th colspan="4"></th>
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
                        <td>
                            @can('supplements.comment')
                                <form method="POST" action="{{ route('remainders.comment', $r->id) }}" class="form-inline flex-nowrap">
                                    @csrf @method('PUT')
                                    <input name="comment" type="text" maxlength="255" value="{{ $r->comment }}" class="form-control form-control-sm" style="min-width: 220px" aria-label="{{ __('supplements.comment') }}">
                                    <button type="submit" class="btn btn-sm btn-outline-primary ml-1" title="{{ __('supplements.save') }}"><i class="fas fa-save"></i></button>
                                </form>
                            @else
                                {{ $r->comment }}
                            @endcan
                        </td>
                        <td>
                            @if ($states->has($r->ticket_barcode))
                                <span class="badge {{ (int) $states[$r->ticket_barcode] === config('ticket_statuses.restrict') ? 'badge-danger' : 'badge-info' }}">{{ __('supplements.ticket_status_' . array_search((int) $states[$r->ticket_barcode], config('ticket_statuses'))) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('supplements.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $remainders->links() }}</div>
    </div>
@endsection
