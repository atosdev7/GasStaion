@extends('layouts.app')

@section('title', __('menu.inbound'))

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('inbound.store') }}">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label for="f_tank_id">{{ __('fuel.tank_id') }}</label>
                    <select id="f_tank_id" name="tank_id" class="form-control @error('tank_id') is-invalid @enderror">
                        @foreach ($tanks as $id => $name)
                            <option value="{{ $id }}" {{ (string) old('tank_id') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('tank_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_inbound_date">{{ __('fuel.inbound_date') }}</label>
                    <input id="f_inbound_date" name="inbound_date" type="date" value="{{ old('inbound_date', date('Y-m-d')) }}" class="form-control @error('inbound_date') is-invalid @enderror">
                    @error('inbound_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_amount_kg">{{ __('fuel.amount_kg') }}</label>
                    <input id="f_amount_kg" name="amount_kg" type="number" step="0.01" value="{{ old('amount_kg') }}" class="form-control @error('amount_kg') is-invalid @enderror">
                    @error('amount_kg') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_cost">{{ __('fuel.cost') }}</label>
                    <input id="f_cost" name="cost" type="number" step="0.0001" value="{{ old('cost') }}" class="form-control @error('cost') is-invalid @enderror">
                    @error('cost') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="f_is_default" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}>
                        <label class="custom-control-label" for="f_is_default">{{ __('fuel.is_default') }}</label>
                    </div>
                    <small class="form-text text-muted">{{ __('fuel.default_hint') }}</small>
                </div>
                <div class="form-group">
                    <label for="f_note">{{ __('fuel.note') }}</label>
                    <input id="f_note" name="note" type="text" value="{{ old('note') }}" class="form-control @error('note') is-invalid @enderror">
                    @error('note') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('fuel.save') }}</button>
                <a href="{{ route('inbound.index') }}" class="btn btn-default">{{ __('fuel.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
