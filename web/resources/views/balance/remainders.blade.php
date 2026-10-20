@extends('layouts.app')

@section('title', __('menu.group_init') . ' - ' . __('menu.init_remainders'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <p class="text-muted">{{ __('initialize.opening_remainder_hint') }}</p>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.opening_remainders') }}</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('balances.remainders.store') }}" class="form-row align-items-end">
                @csrf
                <div class="col-md-2 form-group">
                    <label for="f_rem_fuel">{{ __('initialize.fuel_type') }}</label>
                    <select id="f_rem_fuel" name="fuel_type" class="form-control">
                        @foreach ($fuels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label for="f_rem_kg">{{ __('initialize.amount_kg') }}</label>
                    <input id="f_rem_kg" name="amount_kg" type="number" step="0.01" min="0.01" class="form-control">
                </div>
                <div class="col-md-2 form-group">
                    <label for="f_rem_date">{{ __('initialize.remainder_date') }}</label>
                    <input id="f_rem_date" name="remainder_date" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" class="form-control">
                </div>
                <div class="col-md-4 form-group">
                    <label for="f_rem_comment">{{ __('initialize.comment') }}</label>
                    <input id="f_rem_comment" name="comment" type="text" maxlength="255" class="form-control">
                </div>
                <div class="col-md-2 form-group">
                    <button type="submit" class="btn btn-success btn-block">{{ __('initialize.new') }}</button>
                </div>
            </form>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('initialize.remainder_date') }}</th>
                    <th>{{ __('initialize.barcode') }}</th>
                    <th>{{ __('initialize.fuel_type') }}</th>
                    <th class="text-right">{{ __('initialize.amount_kg') }}</th>
                    <th>{{ __('initialize.comment') }}</th>
                    <th>{{ __('initialize.status') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($remainders as $r)
                    <tr>
                        <td>{{ $r->remainder_date ? substr((string) $r->remainder_date, 0, 10) : substr((string) $r->created_at, 0, 10) }}</td>
                        <td><a href="{{ route('tickets.lookup', ['barcode' => $r->ticket_barcode]) }}">{{ $r->ticket_barcode }}</a></td>
                        <td>{{ $fuels[$r->fuel_type] ?? $r->fuel_type }}</td>
                        <td class="text-right">{{ number_format($r->amount_kg, 2) }}</td>
                        <td>{{ $r->comment }}</td>
                        <td>
                            @if ($states->has($r->ticket_barcode))
                                <span class="badge {{ (int) $states[$r->ticket_barcode] === config('ticket_statuses.restrict') ? 'badge-danger' : 'badge-info' }}">{{ __('initialize.ticket_status_' . array_search((int) $states[$r->ticket_barcode], config('ticket_statuses'))) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">{{ __('initialize.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
