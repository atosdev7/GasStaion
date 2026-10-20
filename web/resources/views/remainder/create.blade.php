@extends('layouts.app')

@section('title', __('supplements.add_remainder'))

@section('content')
    @if ($supplies->isEmpty())
        <div class="alert alert-warning">{{ __('supplements.no_supply_for_remainder') }}</div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('remainders.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label for="f_supplement_id">{{ __('supplements.remainder_of_supply') }}</label>
                    <select id="f_supplement_id" name="supplement_id" class="form-control js-select2 @error('supplement_id') is-invalid @enderror">
                        @foreach ($supplies as $supply)
                            @php($room = round($supply->preset_kg - $supply->out_amount_kg, 2))
                            <option value="{{ $supply->id }}" data-room="{{ $room }}" {{ old('supplement_id') === $supply->id ? 'selected' : '' }}>
                                {{ $supply->supply_day }} &middot; {{ $nozzleLabels[$supply->nozzle_id] ?? $supply->nozzle_id }} &middot; {{ $fuels[$supply->fuel_type] ?? $supply->fuel_type }} &middot; {{ __('supplements.remainder_room', ['kg' => number_format($room, 2)]) }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('supplements.remainder_supply_hint') }}</small>
                    @error('supplement_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="f_amount_kg">{{ __('supplements.amount_kg') }}</label>
                        <input id="f_amount_kg" name="amount_kg" type="number" step="0.01" min="0.01" value="{{ old('amount_kg') }}" class="form-control @error('amount_kg') is-invalid @enderror">
                        @error('amount_kg') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label for="f_remainder_date">{{ __('supplements.remainder_date') }}</label>
                        <input id="f_remainder_date" name="remainder_date" type="date" max="{{ date('Y-m-d') }}" value="{{ old('remainder_date', date('Y-m-d')) }}" class="form-control @error('remainder_date') is-invalid @enderror">
                        @error('remainder_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="form-group">
                    <label for="f_comment">{{ __('supplements.comment') }}</label>
                    <input id="f_comment" name="comment" type="text" maxlength="255" value="{{ old('comment') }}" class="form-control @error('comment') is-invalid @enderror">
                    @error('comment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success" {{ $supplies->isEmpty() ? 'disabled' : '' }}>{{ __('supplements.add_remainder') }}</button>
                <a href="{{ route('remainders.index') }}" class="btn btn-default">{{ __('supplements.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    var supply = $('#f_supplement_id');
    var weight = $('#f_amount_kg');
    var touched = weight.val() !== '';

    weight.on('input', function () { touched = true; });
    // the weight is filled with what the chosen supply can still give, until it is typed by hand
    supply.on('change', function () {
        var room = supply.find('option:selected').data('room');
        if (!touched) {
            weight.val(room ? room : '');
        }
    });
    if (!touched) {
        supply.trigger('change');
    }
});
</script>
@endpush
