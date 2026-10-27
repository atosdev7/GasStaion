@extends('layouts.app')

@section('title', __('menu.group_init') . ' - ' . __('menu.init_ticket_sales'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <p class="text-muted">{{ __('initialize.ticket_sales_hint') }}</p>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.ticket_sales_title') }}</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('balances.ticket-sales.store') }}" class="form-row align-items-end">
                @csrf
                <div class="col-md-2 form-group">
                    <label for="f_ts_fuel">{{ __('initialize.fuel_type') }}</label>
                    <select id="f_ts_fuel" name="fuel_type" class="form-control">
                        @foreach ($fuels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label for="f_ts_spec">{{ __('initialize.ticket_spec') }}</label>
                    <select id="f_ts_spec" name="spec" class="form-control">
                        @foreach ($specs as $key => $kg)
                            <option value="{{ $key }}">{{ $kg }} {{ __('initialize.kg') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label for="f_ts_qty">{{ __('initialize.ticket_quantity') }}</label>
                    <input id="f_ts_qty" name="quantity" type="number" step="1" min="1" class="form-control" required>
                </div>
                <div class="col-md-2 form-group">
                    <label for="f_ts_date">{{ __('initialize.as_of') }}</label>
                    <input id="f_ts_date" name="as_of" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" class="form-control">
                </div>
                <div class="col-md-3 form-group">
                    <label for="f_ts_comment">{{ __('initialize.comment') }}</label>
                    <input id="f_ts_comment" name="comment" type="text" maxlength="255" class="form-control">
                </div>
                <div class="col-md-1 form-group">
                    <button type="submit" class="btn btn-success btn-block">{{ __('initialize.new') }}</button>
                </div>
            </form>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap" id="ticket-sales-table">
                <thead>
                <tr>
                    <th>{{ __('initialize.fuel_type') }}</th>
                    <th>{{ __('initialize.ticket_spec') }}</th>
                    <th>{{ __('initialize.as_of') }}</th>
                    <th class="text-right">{{ __('initialize.ticket_quantity') }}</th>
                    <th class="text-right">{{ __('initialize.ticket_supplied') }}</th>
                    <th class="text-right">{{ __('initialize.ticket_left') }}</th>
                    <th>{{ __('initialize.comment') }}</th>
                    <th class="text-right">{{ __('initialize.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($entries as $entry)
                    @php($row = $entry['row'])
                    <tr>
                        <td>{{ $fuels[$row->fuel_type] ?? $row->fuel_type }}</td>
                        <td>{{ $specs[$row->spec] ?? $row->spec }} {{ __('initialize.kg') }}</td>
                        <td>{{ substr((string) $row->as_of, 0, 10) }}</td>
                        <td class="text-right">{{ number_format($row->quantity) }}</td>
                        <td class="text-right">{{ number_format($entry['supplied']) }}</td>
                        <td class="text-right">{{ number_format($entry['left']) }}</td>
                        <td>{{ $row->comment }}</td>
                        <td class="text-right text-nowrap">
                            <a href="{{ route('balances.ticket-sales.edit', $row->id) }}" class="btn btn-sm btn-outline-primary">{{ __('initialize.edit') }}</a>
                            <form method="POST" action="{{ route('balances.ticket-sales.destroy', $row->id) }}" class="d-inline"
                                  onsubmit="return confirm(@json(__('initialize.opening_delete_confirm')))">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('initialize.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($owed)
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('initialize.ticket_owed_title') }}</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm text-nowrap" id="ticket-owed-table">
                    <thead>
                    <tr>
                        <th>{{ __('initialize.fuel_type') }}</th>
                        <th class="text-right">{{ __('initialize.ticket_left') }}</th>
                        <th class="text-right">{{ __('initialize.ticket_left_kg') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($owed as $fuel => $sum)
                        <tr>
                            <td>{{ $fuels[$fuel] ?? $fuel }}</td>
                            <td class="text-right">{{ number_format($sum['count']) }}</td>
                            <td class="text-right">{{ number_format($sum['kg'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
