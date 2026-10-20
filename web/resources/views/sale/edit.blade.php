@extends('layouts.app')

@section('title', __('sale_edit.title'))

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('ticket-sales.update', $sale->id) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="back" value="{{ $back }}">
            <div class="card-header"><h3 class="card-title">{{ __('sales.sale_type_' . $sale->sale_type) }} &middot; {{ $customer }}</h3></div>
            <div class="card-body">
                <p class="text-muted">{{ __('sale_edit.hint') }}</p>
                <table class="table table-sm w-auto mb-4">
                    <tr><th style="width: 200px">{{ __('sales.fuel_type') }}</th><td>{{ $fuels[$sale->fuel_type] ?? $sale->fuel_type }}</td></tr>
                    <tr><th>{{ __('sales.amount_kg') }}</th><td>{{ number_format($sale->amount_kg, 2) }} {{ __('sales.kg') }}</td></tr>
                    <tr><th>{{ __('sales.price') }}</th><td>{{ number_format($sale->price, 2) }}</td></tr>
                    <tr><th>{{ __('sales.total_amount') }}</th><td>{{ number_format($sale->total_amount, 2) }}</td></tr>
                    <tr><th>{{ __('sales.usage') }}</th><td>{{ __('sales.usage_' . $sale->usage) }}</td></tr>
                    <tr><th>{{ __('sales.sale_made') }}</th><td>{{ $sale->created_at }}@if ($user) &middot; {{ $user }}@endif</td></tr>
                </table>

                <div class="form-group">
                    <label for="f_sale_date">{{ __('sales.sale_date') }}</label>
                    <input id="f_sale_date" name="sale_date" type="date" max="{{ date('Y-m-d') }}" value="{{ old('sale_date', substr((string) $sale->sale_date, 0, 10)) }}" class="form-control @error('sale_date') is-invalid @enderror" style="max-width: 220px">
                    @error('sale_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_comment">{{ __('sales.comment') }}</label>
                    <input id="f_comment" name="comment" type="text" maxlength="255" value="{{ old('comment', $sale->comment) }}" class="form-control @error('comment') is-invalid @enderror">
                    @error('comment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label class="d-block">{{ __('sales.receipt') }}</label>
                    <input type="hidden" name="receipt" value="0">
                    <div class="custom-control custom-checkbox">
                        <input id="f_receipt" name="receipt" type="checkbox" value="1" {{ old('receipt', $sale->receipt) ? 'checked' : '' }} class="custom-control-input">
                        <label for="f_receipt" class="custom-control-label">{{ __('sales.receipt_issued') }}</label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('sales.save') }}</button>
                <a href="{{ $back }}" class="btn btn-default">{{ __('sales.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
