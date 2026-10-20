@extends('layouts.app')

@section('title', __('cards.edit'))

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('cards.update', $card->id) }}">
            @csrf
            @method('PUT')
            <div class="card-header"><h3 class="card-title">{{ __('cards.edit_title', ['card' => $card->uid]) }}</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label>{{ __('cards.uid') }}</label>
                    <input type="text" class="form-control" value="{{ $card->uid }}" disabled style="max-width: 320px">
                </div>
                <div class="form-group">
                    <label>{{ __('cards.fuel_type') }}</label>
                    <input type="text" class="form-control" value="{{ $fuels[$card->fuel_type] ?? $card->fuel_type }}" disabled style="max-width: 320px">
                </div>
                <div class="form-group">
                    <label for="f_holder_name">{{ __('cards.holder') }}</label>
                    <input id="f_holder_name" name="holder_name" type="text" maxlength="100" value="{{ old('holder_name', $card->holder_name) }}" class="form-control @error('holder_name') is-invalid @enderror">
                    @error('holder_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_holder_phone">{{ __('cards.phone') }}</label>
                    <input id="f_holder_phone" name="holder_phone" type="text" maxlength="30" value="{{ old('holder_phone', $card->holder_phone) }}" class="form-control @error('holder_phone') is-invalid @enderror" style="max-width: 320px">
                    @error('holder_phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_comment">{{ __('cards.comment') }}</label>
                    <input id="f_comment" name="comment" type="text" maxlength="255" value="{{ old('comment', $card->comment) }}" class="form-control @error('comment') is-invalid @enderror">
                    @error('comment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('cards.save') }}</button>
                <a href="{{ route('cards.index') }}" class="btn btn-default">{{ __('cards.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
