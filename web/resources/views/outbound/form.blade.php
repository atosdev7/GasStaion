@extends('layouts.app')

@section('title', __('menu.outbounds'))

@section('content')
    <div class="card">
        <form method="POST" action="{{ $outbound->exists ? route('outbound.update', $outbound->id) : route('outbound.store') }}">
            @csrf
            @if ($outbound->exists)
                @method('PUT')
            @endif
            <div class="card-body">
                <div class="form-group">
                    <label for="f_tank_id">{{ __('fuel.tank_id') }}</label>
                    <select id="f_tank_id" name="tank_id" class="form-control @error('tank_id') is-invalid @enderror">
                        @foreach ($tanks as $id => $name)
                            <option value="{{ $id }}" {{ (string) old('tank_id', $outbound->tank_id) === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('tank_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_outbound_date">{{ __('fuel.outbound_date') }}</label>
                    <input id="f_outbound_date" name="outbound_date" type="date" value="{{ old('outbound_date', $outbound->outbound_date ?: date('Y-m-d')) }}" class="form-control @error('outbound_date') is-invalid @enderror">
                    @error('outbound_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_amount_kg">{{ __('fuel.amount_kg') }}</label>
                    <input id="f_amount_kg" name="amount_kg" type="number" step="0.01" value="{{ old('amount_kg', $outbound->amount_kg) }}" class="form-control @error('amount_kg') is-invalid @enderror">
                    @error('amount_kg') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_to_name">{{ __('fuel.to_name') }}</label>
                    <input id="f_to_name" name="to_name" type="text" maxlength="100" value="{{ old('to_name', $outbound->to_name) }}" class="form-control @error('to_name') is-invalid @enderror">
                    @error('to_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_note">{{ __('fuel.note') }}</label>
                    <input id="f_note" name="note" type="text" maxlength="255" value="{{ old('note', $outbound->note) }}" class="form-control @error('note') is-invalid @enderror">
                    @error('note') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('fuel.save') }}</button>
                <a href="{{ route('outbound.index') }}" class="btn btn-default">{{ __('fuel.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
