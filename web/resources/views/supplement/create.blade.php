@extends('layouts.app')

@section('title', __('supplements.record_supplement'))

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('supplements.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label for="f_supplied_at">{{ __('supplements.supplied_at') }}</label>
                    <input id="f_supplied_at" name="supplied_at" type="datetime-local" value="{{ old('supplied_at', date('Y-m-d\TH:i')) }}" class="form-control @error('supplied_at') is-invalid @enderror">
                    @error('supplied_at') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_nozzle_id">{{ __('supplements.nozzle_id') }}</label>
                    <select id="f_nozzle_id" name="nozzle_id" class="form-control @error('nozzle_id') is-invalid @enderror">
                        @foreach ($nozzleOptions as $id => $label)
                            <option value="{{ $id }}" {{ (string) old('nozzle_id') === (string) $id ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('nozzle_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                @if ($costChoices !== null)
                    {{-- the cost per kg: one of the costs of the fuel in the tank of the nozzle (the list follows the nozzle), the default cost of the fuel type chosen to begin with; the fuel is taken from the layer of this cost --}}
                    <div class="form-group">
                        <label for="f_cost">{{ __('supplements.cost') }}</label>
                        <select id="f_cost" name="cost" class="form-control @error('cost') is-invalid @enderror"></select>
                        <small class="form-text text-muted">{{ __('supplements.cost_hint') }}</small>
                        @error('cost') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                @endif
                <div class="form-group">
                    <label for="f_mode">{{ __('supplements.mode') }}</label>
                    <select id="f_mode" name="mode" class="form-control @error('mode') is-invalid @enderror">
                        @foreach ($modes as $mode)
                            <option value="{{ $mode }}" {{ old('mode', 'auto') === $mode ? 'selected' : '' }}>{{ __('supplements.mode_' . $mode) }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('supplements.mode_hint') }}</small>
                    @error('mode') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_ticket_barcodes">{{ __('supplements.ticket_barcodes') }}</label>
                    <textarea id="f_ticket_barcodes" name="ticket_barcodes" rows="5" class="form-control @error('ticket_barcodes') is-invalid @enderror @error('ticket_barcodes.*') is-invalid @enderror" autofocus>{{ old('ticket_barcodes') }}</textarea>
                    <small class="form-text text-muted">{{ __('supplements.supplement_barcode_hint') }}</small>
                    @error('ticket_barcodes') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    @error('ticket_barcodes.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_density">{{ __('supplements.density') }}</label>
                    <input id="f_density" name="density" type="number" step="0.0001" min="0.0001" value="{{ old('density') }}" class="form-control @error('density') is-invalid @enderror">
                    <small class="form-text text-muted">{{ __('supplements.supply_density_hint') }}</small>
                    @error('density') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_out_amount_lt">{{ __('supplements.out_amount_lt') }}</label>
                    <input id="f_out_amount_lt" name="out_amount_lt" type="number" step="0.01" min="0.01" value="{{ old('out_amount_lt') }}" class="form-control @error('out_amount_lt') is-invalid @enderror">
                    @error('out_amount_lt') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success">{{ __('supplements.record_supplement') }}</button>
                <a href="{{ route('supplements.index') }}" class="btn btn-default">{{ __('supplements.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    var densities = @json($densities); // {nozzle id: density of its fuel type}
    var nozzle = $('#f_nozzle_id');
    var density = $('#f_density');
    var touched = density.val() !== '';

    density.on('input', function () { touched = true; });
    // the density of the nozzle's fuel type is filled in when a nozzle is chosen, until the density is typed by hand
    // the cost field lists the costs of the fuel in the tank of the nozzle; the default cost is chosen unless the user chose another that the tank has
    var costChoices = @json($costChoices);
    var oldCost = @json(old('cost'));
    var cost = $('#f_cost');
    var costNozzle = null;
    function fillCostList() {
        if (!cost.length) return;
        var keep = costNozzle === nozzle.val() && cost.val() !== '' ? cost.val() : (costNozzle === null && oldCost !== null ? String(oldCost) : '');
        costNozzle = nozzle.val();
        var list = (costChoices && costChoices[nozzle.val()]) || [];
        cost.empty();
        var chosen = null, fallback = null;
        $.each(list, function (i, c) {
            var text = Number(c.cost).toFixed(4) + (c.kg > 0 ? '  (' + Number(c.kg).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' kg)' : '') + (c.default ? '  *' : '');
            var option = $('<option>').val(c.cost).text(text);
            cost.append(option);
            if (keep !== '' && Number(c.cost) === Number(keep)) chosen = option;
            if (c['default']) fallback = option;
        });
        if (!list.length) cost.append($('<option>').val('').text('-'));
        (chosen || fallback || cost.find('option').first()).prop('selected', true);
    }
    nozzle.on('change', fillCostList);
    fillCostList();
    nozzle.on('change', function () {
        var d = densities[nozzle.val()];
        if (!touched) {
            density.val(d ? d : '');
        }
    });
    if (!touched) {
        nozzle.trigger('change');
    }
});
</script>
@endpush
