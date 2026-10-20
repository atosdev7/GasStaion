@extends('layouts.app')

@section('title', __('cards.reset_pin'))

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('cards.pin.save', $card->id) }}">
            @csrf
            <div class="card-header"><h3 class="card-title">{{ __('cards.pin_title', ['card' => $card->label]) }}</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label for="f_pin">{{ __('cards.new_pin') }}</label>
                    <input id="f_pin" name="pin" type="password" inputmode="numeric" maxlength="8" autocomplete="off" class="form-control @error('pin') is-invalid @enderror" style="max-width: 220px">
                    <small class="form-text text-muted">{{ __('cards.pin_hint') }}</small>
                    @error('pin') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('cards.save') }}</button>
                <a href="{{ route('cards.index') }}" class="btn btn-default">{{ __('cards.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
