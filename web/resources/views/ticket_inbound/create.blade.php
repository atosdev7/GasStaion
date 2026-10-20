@extends('layouts.app')

@section('title', __('tickets.blank_inbound'))

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('ticket-inbound.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label for="f_fuel_type">{{ __('tickets.fuel_type') }}</label>
                    <select id="f_fuel_type" name="fuel_type" class="form-control @error('fuel_type') is-invalid @enderror">
                        @foreach ($fuels as $key => $name)
                            <option value="{{ $key }}" {{ (string) old('fuel_type') === (string) $key ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('fuel_type') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_spec">{{ __('tickets.spec') }}</label>
                    <select id="f_spec" name="spec" class="form-control @error('spec') is-invalid @enderror">
                        @foreach ($specs as $key => $kg)
                            <option value="{{ $key }}" {{ (string) old('spec') === (string) $key ? 'selected' : '' }}>{{ $kg }} {{ __('tickets.kg') }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('tickets.spec_hint') }}</small>
                    @error('spec') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_quantity">{{ __('tickets.quantity') }}</label>
                    <input id="f_quantity" name="quantity" type="number" step="1" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror">
                    @error('quantity') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_inbound_date">{{ __('tickets.inbound_date') }}</label>
                    <input id="f_inbound_date" name="inbound_date" type="date" value="{{ old('inbound_date', date('Y-m-d')) }}" class="form-control @error('inbound_date') is-invalid @enderror">
                    @error('inbound_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_note">{{ __('tickets.note') }}</label>
                    <input id="f_note" name="note" type="text" value="{{ old('note') }}" class="form-control @error('note') is-invalid @enderror">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('tickets.save') }}</button>
                <a href="{{ route('ticket-inbound.index') }}" class="btn btn-default">{{ __('tickets.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
