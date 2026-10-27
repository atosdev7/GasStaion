@extends('layouts.app')

@section('title', __('menu.ticket_sales'))

@section('content')
    @if ($stock->isEmpty())
        <div class="alert alert-warning">{{ __('sales.no_sale_stock') }}</div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('ticket-sales.store') }}" id="f_form">
            @csrf
            <input type="hidden" name="sale_type" value="ticket">
            <div class="card-body">
                <div class="row">
                    <div class="form-group col-md-4" id="f_customer_part">
                        @include('sale._customer')
                    </div>
                    <div class="form-group col-md-4">
                        <label for="f_sale_date">{{ __('sales.sale_date') }}</label>
                        <input id="f_sale_date" name="sale_date" type="date" min="{{ \App\Support\Sales\SaleAccess::earliestDate(auth()->user()) }}" max="{{ date('Y-m-d') }}" value="{{ old('sale_date', date('Y-m-d')) }}" class="form-control @error('sale_date') is-invalid @enderror">
                        @error('sale_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="f_fuel_type">{{ __('sales.fuel_type') }}</label>
                        <select id="f_fuel_type" name="fuel_type" class="form-control @error('fuel_type') is-invalid @enderror">
                            @foreach ($stock->pluck('fuel_type')->unique() as $fuelType)
                                <option value="{{ $fuelType }}" data-price="{{ $prices[$fuelType] ?? 0 }}"
                                        {{ old('fuel_type') === $fuelType ? 'selected' : '' }}>
                                    {{ $fuels[$fuelType] ?? $fuelType }}
                                </option>
                            @endforeach
                        </select>
                        @error('fuel_type') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    @can('costs.view')
                        {{-- the cost is one of the costs of the fuel in the tanks (the list follows the fuel type), starting with the default cost of the fuel type; the assistant never sees it (the server sets it) --}}
                        <div class="form-group col-md-4">
                            <label for="f_cost">{{ __('sales.cost') }}</label>
                            <select id="f_cost" name="cost" class="form-control @error('cost') is-invalid @enderror"></select>
                            @error('cost') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    @endcan
                    <div class="form-group col-md-4">
                        <label for="f_price">{{ __('sales.price') }}</label>
                        <input id="f_price" name="price" type="number" step="0.01" min="0" value="{{ old('price') }}" class="form-control @error('price') is-invalid @enderror">
                        @error('price') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="card card-outline card-primary">
                    <div class="card-header"><h3 class="card-title">{{ __('sales.ticket_lines') }}</h3></div>
                    <div class="card-body" id="f_add_item">
                        <div class="row">
                            <div class="col-md-4 border-right">
                                <h6 class="font-weight-bold mb-3">{{ __('sales.add_item') }}</h6>
                                <div class="form-group">
                                    <label for="f_item_spec">{{ __('sales.spec') }}</label>
                                    <select id="f_item_spec" class="form-control"></select>
                                </div>
                                <div class="form-group">
                                    <label for="f_item_start">{{ __('sales.start_serial') }}</label>
                                    <input id="f_item_start" type="number" step="1" min="1" class="form-control" placeholder="{{ __('sales.fifo_placeholder') }}">
                                </div>
                                <div class="form-group">
                                    <label for="f_item_quantity">{{ __('sales.quantity') }}</label>
                                    <input id="f_item_quantity" type="number" step="1" min="1" value="1" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="f_item_end">{{ __('sales.end_serial') }}</label>
                                    <input id="f_item_end" type="text" readonly class="form-control">
                                </div>
                                <div id="f_item_result" class="mb-2"></div>
                                <button type="button" id="f_check_item" class="btn btn-outline-primary">
                                    <i class="fas fa-check"></i> {{ __('sales.check_item') }}
                                </button>
                                <button type="button" id="f_add_line" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> {{ __('sales.add_item') }}
                                </button>
                            </div>
                            <div class="col-md-8">
                                <table class="table table-sm table-bordered" id="f_lines">
                                    <thead>
                                    <tr>
                                        <th>{{ __('sales.spec') }}</th>
                                        <th class="text-right">{{ __('sales.quantity') }}</th>
                                        <th class="text-right">{{ __('sales.start_serial') }}</th>
                                        <th class="text-right">{{ __('sales.end_serial') }}</th>
                                        <th class="text-right">{{ __('sales.amount_kg') }}</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                                <div id="f_items_empty" class="text-muted mb-2">{{ __('sales.sale_items_empty') }}</div>
                                @error('lines') <div class="text-danger mb-3">{{ $message }}</div> @enderror
                                <div class="text-right border-top pt-2">
                                    <strong>{{ __('sales.amount_kg') }}</strong>
                                    <input id="f_amount_kg_display" type="text" readonly class="form-control-plaintext font-weight-bold text-right d-inline-block" style="width: 8rem">
                                </div>
                            </div>
                        </div>
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
                        <small id="f_paid_warning" class="form-text text-danger" style="display: none">{{ __('sales.paid_more_warning') }}</small>
                    </div>
                    <div class="form-group col-md-4 f_payment_part">
                        <label>{{ __('sales.unpaid_amount') }}</label>
                        <input id="f_unpaid_amount_display" type="text" readonly class="form-control-plaintext">
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-4 f_payment_part">
                        <label for="f_payment_method">{{ __('sales.payment_method') }}</label>
                        <select id="f_payment_method" name="payment_method" class="form-control @error('payment_method') is-invalid @enderror">
                            <option value="cash" {{ old('payment_method', 'cash') === 'cash' ? 'selected' : '' }}>{{ __('sales.payment_cash') }}</option>
                            <option value="bank" {{ old('payment_method') === 'bank' ? 'selected' : '' }}>{{ __('sales.payment_bank') }}</option>
                        </select>
                        @error('payment_method') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
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
                <button type="submit" class="btn btn-success">{{ __('menu.ticket_sales') }}</button>
                <a href="{{ route('ticket-sales.index') }}" class="btn btn-default">{{ __('sales.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var specs = @json($specs); // {spec_key: kg}
    var specLabels = @json(collect($specs)->map(function ($kg) { return $kg . ' ' . __('sales.kg'); }));
    var serials = @json($serials); // {fuel: {spec: [[first, last], ...]}} the published tickets, as runs of consecutive serials
    var prefixes = @json($barcodePrefixes); // {fuel: {spec: 'sign and code'}}: a barcode is these 7 digits and the serial padded to 9
    var oldLines = @json(old('lines', []));
    var notEnough = @json(__('sales.not_enough_sale_stock'));
    var textValid = @json(__('sales.item_valid', ['start' => ':start', 'end' => ':end']));
    var textNotValid = @json(__('sales.item_not_valid', ['start' => ':start', 'end' => ':end']));

    var fuelType = document.getElementById('f_fuel_type');
    var itemSpec = document.getElementById('f_item_spec');
    var itemStart = document.getElementById('f_item_start');
    var itemQuantity = document.getElementById('f_item_quantity');
    var itemEnd = document.getElementById('f_item_end');
    var itemResult = document.getElementById('f_item_result');
    var addItemBtn = document.getElementById('f_add_line');
    var checkItemBtn = document.getElementById('f_check_item');
    var linesBody = document.querySelector('#f_lines tbody');
    var itemsEmpty = document.getElementById('f_items_empty');
    var amountKgDisplay = document.getElementById('f_amount_kg_display');
    var price = document.getElementById('f_price');
    var cost = document.getElementById('f_cost');
    var totalDisplay = document.getElementById('f_total_amount_display');
    var usage = document.getElementById('f_usage');
    var paymentParts = document.querySelectorAll('.f_payment_part');
    var paymentMethod = document.getElementById('f_payment_method');
    var paidAmount = document.getElementById('f_paid_amount');
    var unpaidAmountDisplay = document.getElementById('f_unpaid_amount_display');
    var paidWarning = document.getElementById('f_paid_warning');
    var customerPart = document.getElementById('f_customer_part');
    var items = []; // {spec, quantity, start (a number, or null for the lowest serials)}
    var totalKg = 0;

    // Which serials each item gets, worked out in the order of the items like the server does it: an item with a start serial takes
    // that run, an item without takes the lowest ones still free. Returns {results: [{ok, start, end}], free: {spec: [[first, last], ...]}}
    // where free is what is left after all the items.
    function allocate(list) {
        var free = {};
        var published = serials[fuelType.value] || {};
        function runs(spec) {
            if (!free[spec]) {
                free[spec] = (published[spec] || []).map(function (r) { return [r[0], r[1]]; });
            }
            return free[spec];
        }

        var results = list.map(function (item) {
            return take(runs(item.spec), item.start, item.quantity);
        });

        return {results: results, free: free, runs: runs};
    }

    // Takes quantity serials out of the free runs: from start (all of them must be free), or the lowest ones when there is no start.
    function take(list, start, quantity) {
        var result = {ok: false, start: null, end: null};

        if (start) {
            var last = start + quantity - 1;
            for (var i = 0; i < list.length; i++) {
                if (list[i][0] <= start && last <= list[i][1]) {
                    var run = list[i];
                    var rest = [];
                    if (run[0] < start) rest.push([run[0], start - 1]);
                    if (last < run[1]) rest.push([last + 1, run[1]]);
                    list.splice.apply(list, [i, 1].concat(rest));
                    result = {ok: true, start: start, end: last};
                    break;
                }
            }
        } else {
            var count = 0;
            list.forEach(function (r) { count += r[1] - r[0] + 1; });
            if (count >= quantity) {
                var need = quantity;
                var first = null;
                var end = null;
                while (need > 0) {
                    var r = list[0];
                    var n = Math.min(need, r[1] - r[0] + 1);
                    if (first === null) first = r[0];
                    end = r[0] + n - 1;
                    if (n === r[1] - r[0] + 1) { list.shift(); } else { r[0] += n; }
                    need -= n;
                }
                result = {ok: true, start: first, end: end};
            }
        }

        return result;
    }

    // The full barcode of a serial of a spec (the serial alone when the spec has no code).
    function barcodeOf(spec, serial) {
        var prefix = (prefixes[fuelType.value] || {})[spec];

        return prefix ? prefix + ('000000000' + serial).slice(-9) : String(serial);
    }

    function formValues() {
        var quantity = parseInt(itemQuantity.value, 10);
        var start = parseInt(itemStart.value, 10);

        return {spec: itemSpec.value, quantity: isNaN(quantity) ? 0 : quantity, start: isNaN(start) || start < 1 ? null : start};
    }

    // What the form as it stands would take, after the items already in the table (null when the count is not a number).
    function checkForm() {
        var v = formValues();
        if (!v.spec || v.quantity < 1) {
            return null;
        }
        var state = allocate(items);

        return {values: v, result: take(state.runs(v.spec), v.start, v.quantity)};
    }

    function say(text, good) {
        itemResult.className = 'mb-2 ' + (good ? 'text-success' : 'text-danger');
        itemResult.textContent = text;
    }

    function clearResult() {
        itemResult.className = 'mb-2';
        itemResult.textContent = '';
    }

    // The first free serial of the chosen spec goes into the start field.
    function fillStart() {
        var state = allocate(items);
        var list = itemSpec.value ? state.runs(itemSpec.value) : [];
        itemStart.value = list.length ? list[0][0] : '';
        showEnd();
    }

    // The end serial follows from the start and the count.
    function showEnd() {
        var v = formValues();
        if (v.start && v.quantity > 0) {
            itemEnd.value = v.start + v.quantity - 1;
        } else {
            var checked = checkForm();
            itemEnd.value = checked && checked.result.ok ? checked.result.end : '';
        }
        clearResult();
    }

    function checkItem() {
        var checked = checkForm();
        if (!checked) {
            itemQuantity.focus();
            return null;
        }
        var v = checked.values;
        var r = checked.result;
        var from = v.start || (r.ok ? r.start : '');
        var to = v.start ? v.start + v.quantity - 1 : (r.ok ? r.end : '');
        if (r.ok) {
            say(textValid.replace(':start', r.start).replace(':end', r.end), true);
        } else {
            say(v.start ? textNotValid.replace(':start', from).replace(':end', to) : notEnough, false);
        }

        return checked;
    }

    function fillSpecOptions() {
        var published = serials[fuelType.value] || {};
        var html = '';
        for (var key in specs) {
            var count = 0;
            (published[key] || []).forEach(function (r) { count += r[1] - r[0] + 1; });
            if (count > 0) {
                html += '<option value="' + key + '">' + specLabels[key] + ' (' + count + ')</option>';
            }
        }
        itemSpec.innerHTML = html;
        addItemBtn.disabled = html === '';
        checkItemBtn.disabled = html === '';
    }

    function render() {
        var results = allocate(items).results;
        var html = '';
        totalKg = 0;
        items.forEach(function (item, i) {
            var a = results[i];
            var kg = (specs[item.spec] || 0) * item.quantity;
            totalKg += kg;
            html += '<tr' + (a.ok ? '' : ' class="table-danger"') + '>' +
                '<td>' +
                    '<input type="hidden" name="lines[' + i + '][spec]" value="' + item.spec + '">' +
                    '<input type="hidden" name="lines[' + i + '][quantity]" value="' + item.quantity + '">' +
                    (item.start ? '<input type="hidden" name="lines[' + i + '][start_serial]" value="' + item.start + '">' : '') +
                    specLabels[item.spec] + '</td>' +
                '<td class="text-right">' + item.quantity + '</td>' +
                (a.ok
                    ? '<td class="text-right">' + barcodeOf(item.spec, a.start) + '</td><td class="text-right">' + barcodeOf(item.spec, a.end) + '</td>'
                    : '<td colspan="2" class="text-danger">' + notEnough + '</td>') +
                '<td class="text-right">' + kg.toFixed(2) + '</td>' +
                '<td class="text-center"><button type="button" class="btn btn-xs btn-outline-danger f_remove_item" data-index="' + i + '"><i class="fas fa-times"></i></button></td>' +
                '</tr>';
        });
        linesBody.innerHTML = html;
        itemsEmpty.style.display = items.length ? 'none' : '';
        fillStart();
        recalc();
    }

    // An item is added only when the check says its serials are all available.
    function addItem() {
        var checked = checkItem();
        if (!checked || !checked.result.ok) {
            return;
        }
        items.push({spec: checked.values.spec, quantity: checked.values.quantity, start: checked.values.start});
        itemQuantity.value = '1';
        render();
    }

    function totalAmount() {
        var p = parseFloat(price.value);
        return isNaN(p) ? 0 : totalKg * p;
    }

    function fillDefaultPaidAmount() {
        var total = totalAmount();
        paidAmount.value = (paymentMethod.value === 'cash' ? total : 0).toFixed(2);
    }

    function recalcUnpaidDisplay() {
        var total = totalAmount();
        var paid = parseFloat(paidAmount.value);
        if (isNaN(paid)) paid = 0;
        unpaidAmountDisplay.value = Math.max(total - paid, 0).toFixed(2);
        // more paid than the total is probably a wrong value (the sale keeps the total as paid)
        paidWarning.style.display = paid > total + 0.004 ? '' : 'none';
    }

    function togglePaymentGroup() {
        for (var i = 0; i < paymentParts.length; i++) {
            paymentParts[i].style.display = usage.value === 'sale' ? '' : 'none';
        }
        // own use and other use are always the customer "Individual"
        customerPart.style.display = usage.value === 'self' || usage.value === 'other' ? 'none' : '';
    }

    function fillPriceAndCostFromFuelType() {
        var opt = fuelType.options[fuelType.selectedIndex];
        if (!opt) return;
        if (!price.value) price.value = opt.getAttribute('data-price') || '';
        fillCostList();
    }

    // The cost field lists the costs of the fuel in the tanks for the chosen fuel type; the default cost of the fuel type is chosen unless the user chose another.
    var costChoices = @json($costChoices);
    var oldCost = @json(old('cost'));
    var costFuel = null;
    function fillCostList() {
        if (!cost) return;
        // what was chosen stays while the fuel type stays (after a failed save: what was sent)
        var keep = costFuel === fuelType.value && cost.value !== '' ? cost.value : (costFuel === null && oldCost !== null ? String(oldCost) : '');
        costFuel = fuelType.value;
        var list = costChoices[fuelType.value] || [];
        cost.innerHTML = '';
        var chosen = null, fallback = null;
        for (var i = 0; i < list.length; i++) {
            var option = document.createElement('option');
            option.value = list[i].cost;
            option.textContent = Number(list[i].cost).toFixed(4) + (list[i].kg > 0 ? '  (' + Number(list[i].kg).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' kg)' : '')
                + (list[i].default ? '  *' : '');
            cost.appendChild(option);
            if (keep !== '' && Number(list[i].cost) === Number(keep)) chosen = option;
            if (list[i].default) fallback = option;
        }
        if (!list.length) {
            var none = document.createElement('option');
            none.value = '';
            none.textContent = '-';
            cost.appendChild(none);
        }
        var pick = chosen || fallback || cost.options[0];
        if (pick) pick.selected = true;
    }

    function recalc() {
        amountKgDisplay.value = totalKg.toFixed(2);
        totalDisplay.value = totalAmount().toFixed(2);
        fillDefaultPaidAmount();
        recalcUnpaidDisplay();
    }

    addItemBtn.addEventListener('click', addItem);
    checkItemBtn.addEventListener('click', checkItem);
    itemSpec.addEventListener('change', fillStart);
    itemStart.addEventListener('input', showEnd);
    itemQuantity.addEventListener('input', showEnd);
    [itemQuantity, itemStart].forEach(function (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addItem();
            }
        });
    });
    linesBody.addEventListener('click', function (e) {
        var button = e.target.closest ? e.target.closest('.f_remove_item') : null;
        if (button) {
            items.splice(parseInt(button.getAttribute('data-index'), 10), 1);
            render();
        }
    });
    fuelType.addEventListener('change', function () {
        fillPriceAndCostFromFuelType();
        fillSpecOptions();
        render();
    });
    price.addEventListener('input', recalc);
    paymentMethod.addEventListener('change', function () {
        fillDefaultPaidAmount();
        recalcUnpaidDisplay();
    });
    paidAmount.addEventListener('input', recalcUnpaidDisplay);
    usage.addEventListener('change', togglePaymentGroup);

    // the items of a form that came back with an error are put back
    (Array.isArray(oldLines) ? oldLines : Object.keys(oldLines).map(function (k) { return oldLines[k]; })).forEach(function (line) {
        var quantity = parseInt(line.quantity, 10);
        var start = parseInt(line.start_serial, 10);
        if (specs.hasOwnProperty(line.spec) && quantity > 0) {
            items.push({spec: String(line.spec), quantity: quantity, start: isNaN(start) || start < 1 ? null : start});
        }
    });

    fillPriceAndCostFromFuelType();
    fillSpecOptions();
    togglePaymentGroup();
    render();
})();
</script>
@endpush
