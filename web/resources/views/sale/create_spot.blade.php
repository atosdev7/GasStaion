@extends('layouts.app')

@section('title', __('sales.spot_sale'))

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('ticket-sales.store') }}">
            @csrf
            <input type="hidden" name="sale_type" value="spot">
            <div class="card-body">
                <div class="row">
                    <div class="form-group col-md-4">
                        @include('sale._customer')
                    </div>
                    <div class="form-group col-md-4">
                        <label for="f_sale_date">{{ __('sales.sale_date') }}</label>
                        <input id="f_sale_date" name="sale_date" type="date" max="{{ date('Y-m-d') }}" value="{{ old('sale_date', date('Y-m-d')) }}" class="form-control @error('sale_date') is-invalid @enderror">
                        @error('sale_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="f_fuel_type">{{ __('sales.fuel_type') }}</label>
                        <select id="f_fuel_type" name="fuel_type" class="form-control @error('fuel_type') is-invalid @enderror">
                            @foreach ($fuels as $key => $name)
                                <option value="{{ $key }}" data-price="{{ $prices[$key] ?? 0 }}" data-cost="{{ $costs[$key] ?? '' }}"
                                        {{ old('fuel_type') === $key ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        @error('fuel_type') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label for="f_cost">{{ __('sales.cost') }}</label>
                        <input id="f_cost" name="cost" type="number" step="0.0001" min="0" value="{{ old('cost') }}" class="form-control @error('cost') is-invalid @enderror">
                        @error('cost') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label for="f_price">{{ __('sales.price') }}</label>
                        <input id="f_price" name="price" type="number" step="0.01" min="0" value="{{ old('price') }}" class="form-control @error('price') is-invalid @enderror">
                        @error('price') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="f_amount_kg">{{ __('sales.amount_kg') }}</label>
                        <input id="f_amount_kg" name="amount_kg" type="number" step="0.01" min="0.01" value="{{ old('amount_kg') }}" class="form-control @error('amount_kg') is-invalid @enderror">
                        @error('amount_kg') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="form-group col-md-4">
                        <label>{{ __('sales.total_amount') }}</label>
                        <input id="f_total_amount_display" type="text" readonly class="form-control-plaintext">
                    </div>
                    <div class="form-group col-md-4 f_payment_part">
                        <label for="f_paid_amount">{{ __('sales.paid_amount') }}</label>
                        <input id="f_paid_amount" name="paid_amount" type="number" step="0.01" min="0" value="{{ old('paid_amount') }}" class="form-control @error('paid_amount') is-invalid @enderror">
                        @error('paid_amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group col-md-4 f_payment_part">
                        <label>{{ __('sales.unpaid_amount') }}</label>
                        <input id="f_unpaid_amount_display" type="text" readonly class="form-control-plaintext">
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-4 f_payment_part">
                        {{-- a spot sale is always cash --}}
                        <label>{{ __('sales.payment_method') }}</label>
                        <input type="text" readonly value="{{ __('sales.payment_cash') }}" class="form-control-plaintext">
                        <small class="form-text text-muted">{{ __('sales.spot_sale_cash_hint') }}</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label class="d-block">{{ __('sales.receipt') }}</label>
                        <input type="hidden" name="receipt" value="0">
                        <div class="custom-control custom-checkbox mt-1">
                            <input id="f_receipt" name="receipt" type="checkbox" value="1" {{ old('receipt') ? 'checked' : '' }} class="custom-control-input @error('receipt') is-invalid @enderror">
                            <label for="f_receipt" class="custom-control-label">{{ __('sales.receipt_issued') }}</label>
                        </div>
                        @error('receipt') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="f_usage">{{ __('sales.usage') }}</label>
                        <select id="f_usage" name="usage" class="form-control @error('usage') is-invalid @enderror">
                            <option value="sale" {{ old('usage', 'sale') === 'sale' ? 'selected' : '' }}>{{ __('sales.usage_sale') }}</option>
                            <option value="self" {{ old('usage') === 'self' ? 'selected' : '' }}>{{ __('sales.usage_self') }}</option>
                            <option value="other" {{ old('usage') === 'other' ? 'selected' : '' }}>{{ __('sales.usage_other') }}</option>
                            <option value="ticket_loan" {{ old('usage') === 'ticket_loan' ? 'selected' : '' }}>{{ __('sales.usage_ticket_loan') }}</option>
                        </select>
                        @error('usage') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group col-md-8">
                        <label for="f_comment">{{ __('sales.comment') }}</label>
                        <input id="f_comment" name="comment" type="text" value="{{ old('comment') }}" class="form-control @error('comment') is-invalid @enderror">
                        @error('comment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success">{{ __('sales.spot_sale') }}</button>
                <a href="{{ route('spot-sales.index') }}" class="btn btn-default">{{ __('sales.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var fuelType = document.getElementById('f_fuel_type');
    var amountKg = document.getElementById('f_amount_kg');
    var price = document.getElementById('f_price');
    var cost = document.getElementById('f_cost');
    var totalDisplay = document.getElementById('f_total_amount_display');
    var paidAmount = document.getElementById('f_paid_amount');
    var unpaidAmountDisplay = document.getElementById('f_unpaid_amount_display');
    var usage = document.getElementById('f_usage');
    var paymentParts = document.querySelectorAll('.f_payment_part');

    function totalAmount() {
        var kg = parseFloat(amountKg.value);
        var p = parseFloat(price.value);
        return (isNaN(kg) || isNaN(p)) ? 0 : kg * p;
    }

    function recalc() {
        var total = totalAmount();
        totalDisplay.value = total.toFixed(2);
        // spot sales are always cash, paid in full by default.
        if (!paidAmount.dataset.touched) {
            paidAmount.value = total.toFixed(2);
        }
        var paid = parseFloat(paidAmount.value);
        if (isNaN(paid)) paid = 0;
        unpaidAmountDisplay.value = Math.max(total - paid, 0).toFixed(2);
    }

    function fillPriceAndCostFromFuelType() {
        var opt = fuelType.options[fuelType.selectedIndex];
        if (!opt) return;
        if (!price.value) price.value = opt.getAttribute('data-price') || '';
        if (!cost.value) cost.value = opt.getAttribute('data-cost') || '';
    }

    // only a sale has a paid and an unpaid amount
    function togglePaymentParts() {
        for (var i = 0; i < paymentParts.length; i++) {
            paymentParts[i].style.display = usage.value === 'sale' ? '' : 'none';
        }
    }

    usage.addEventListener('change', togglePaymentParts);
    fuelType.addEventListener('change', function () {
        fillPriceAndCostFromFuelType();
        recalc();
    });
    amountKg.addEventListener('input', recalc);
    price.addEventListener('input', recalc);
    paidAmount.addEventListener('input', function () {
        paidAmount.dataset.touched = '1';
        recalc();
    });

    fillPriceAndCostFromFuelType();
    togglePaymentParts();
    recalc();
})();
</script>
@endpush
