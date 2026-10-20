@extends('layouts.app')

@section('title', __('cards.topup_title'))

@section('content')
    @if ($cards->isEmpty())
        <div class="alert alert-warning">{{ __('cards.no_cards') }}</div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('card-topups.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label for="f_card_id">{{ __('cards.card') }}</label>
                    <select id="f_card_id" name="card_id" class="form-control js-select2 @error('card_id') is-invalid @enderror">
                        <option value="">{{ __('cards.choose_card') }}</option>
                        @foreach ($cards as $card)
                            <option value="{{ $card->id }}" data-fuel="{{ $fuels[$card->fuel_type] ?? $card->fuel_type }}" data-price="{{ $prices[$card->fuel_type] ?? 0 }}" data-balance="{{ number_format($balances[$card->id] ?? 0, 2, '.', '') }}"
                                {{ old('card_id', $chosen) === $card->id ? 'selected' : '' }}>{{ $card->label }} &middot; {{ $fuels[$card->fuel_type] ?? $card->fuel_type }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted" id="card-info"></small>
                    @error('card_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="f_amount_kg">{{ __('cards.amount_kg') }}</label>
                        <input id="f_amount_kg" name="amount_kg" type="number" step="0.01" min="0.01" value="{{ old('amount_kg') }}" class="form-control @error('amount_kg') is-invalid @enderror">
                        @error('amount_kg') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label for="f_price">{{ __('cards.price') }}</label>
                        <input id="f_price" name="price" type="number" step="0.01" min="0" value="{{ old('price') }}" class="form-control @error('price') is-invalid @enderror">
                        @error('price') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label for="f_total">{{ __('cards.total_amount') }}</label>
                        <input id="f_total" type="text" readonly class="form-control">
                    </div>
                </div>
                <small class="text-muted">{{ __('cards.payment_cash_only') }}</small>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success" {{ $cards->isEmpty() ? 'disabled' : '' }}>{{ __('cards.top_up') }}</button>
                <a href="{{ route('card-topups.index') }}" class="btn btn-default">{{ __('cards.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var card = $('#f_card_id');
            var kg = $('#f_amount_kg');
            var price = $('#f_price');
            var priceTouched = price.val() !== '';

            function total() {
                var t = parseFloat(kg.val()) * parseFloat(price.val());
                $('#f_total').val(isNaN(t) ? '' : t.toFixed(2));
            }
            // the price of the fuel of the card is the default, free to change
            card.on('change', function () {
                var o = card.find('option:selected');
                $('#card-info').text(o.val() ? o.data('fuel') + ' - ' + @json(__('cards.balance_now')) + ': ' + o.data('balance') + ' kg' : '');
                if (!priceTouched && o.val()) {
                    price.val(parseFloat(o.data('price')).toFixed(2));
                }
                total();
            });
            price.on('input', function () { priceTouched = true; total(); });
            kg.on('input', total);
            card.trigger('change');
        });
    </script>
@endpush
