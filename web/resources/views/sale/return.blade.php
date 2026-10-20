@extends('layouts.app')

@section('title', __('sale_return.title'))

@section('content')
    @if ($errors->has('sale'))
        <div class="alert alert-danger">{{ $errors->first('sale') }}</div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('sale_return.sale') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm mb-0">
                <tr><th style="width: 220px">{{ __('sales.sale_date') }}</th><td>{{ $sale->sale_date }}</td></tr>
                <tr><th>{{ __('sales.customer_id') }}</th><td>{{ $customer }}</td></tr>
                <tr><th>{{ __('sales.sale_type') }}</th><td>{{ __('sales.sale_type_' . $sale->sale_type) }} / {{ __('sales.usage_' . $sale->usage) }}</td></tr>
                <tr><th>{{ __('sales.fuel_type') }}</th><td>{{ $fuels[$sale->fuel_type] ?? $sale->fuel_type }}</td></tr>
                <tr><th>{{ __('sales.amount_kg') }}</th><td>{{ number_format($sale->amount_kg, 2) }}@if ($returnedKg > 0) ({{ __('sale_return.returned_so_far', ['kg' => number_format($returnedKg, 2)]) }})@endif</td></tr>
                <tr><th>{{ __('sales.price') }}</th><td>{{ number_format($sale->price, 2) }}</td></tr>
                <tr><th>{{ __('sales.total_amount') }}</th><td>{{ number_format($sale->total_amount, 2) }}</td></tr>
                <tr><th>{{ __('sales.paid_amount') }}</th><td>{{ number_format($sale->paid_amount, 2) }} ({{ __('sales.payment_' . $sale->payment_method) }})</td></tr>
                @if ($debtBalance !== null)
                    <tr><th>{{ __('sale_return.still_owed') }}</th><td>{{ number_format($debtBalance, 2) }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('sale_return.unused') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('sales.spec') }}</th><th class="text-right">{{ __('sales.tickets') }}</th></tr></thead>
                <tbody>
                @forelse ($unused as $row)
                    <tr>
                        <td>{{ $row->spec !== null ? config('ticket_specs.' . $row->spec) : number_format($row->amount_kg, 2) }} {{ __('sales.kg') }}</td>
                        <td class="text-right">{{ $row->n }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted">{{ __('sale_return.none_unused') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('ticket-sales.return.store', $sale->id) }}">
            @csrf
            <div class="card-body">
                @if ($wholeOnly)
                    <div class="alert alert-info">{{ __('sale_return.whole_only_hint') }}</div>
                @endif
                <div class="form-group">
                    <label for="f_ticket_barcodes">{{ __('sales.ticket_barcodes') }}</label>
                    <textarea id="f_ticket_barcodes" name="ticket_barcodes" rows="5" class="form-control @error('ticket_barcodes') is-invalid @enderror @error('ticket_barcodes.*') is-invalid @enderror" autofocus>{{ old('ticket_barcodes') }}</textarea>
                    <small class="form-text text-muted">{{ __('sale_return.barcodes_hint') }}</small>
                    @error('ticket_barcodes') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    @error('ticket_barcodes.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="f_all" name="all" value="1" {{ old('all') ? 'checked' : '' }}>
                    <label class="form-check-label" for="f_all">{{ __('sale_return.all') }}</label>
                </div>
                <div class="form-group">
                    <label for="f_comment">{{ __('sale_return.comment') }}</label>
                    <input id="f_comment" name="comment" type="text" maxlength="255" value="{{ old('comment') }}" class="form-control @error('comment') is-invalid @enderror">
                    @error('comment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <small class="form-text text-muted">{{ __('sale_return.money_hint') }}</small>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-warning"><i class="fas fa-undo"></i> {{ __('sale_return.submit') }}</button>
                <a href="{{ route(App\Http\Controllers\Sales\SaleController::listRoute($sale->sale_type)) }}" class="btn btn-default">{{ __('sales.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
