@extends('layouts.app')

@section('title', __('tickets.blank_inbound'))

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('ticket-inbound.update', $inbound->id) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-group">
                    <label>{{ __('tickets.fuel_type') }}</label>
                    <input type="text" class="form-control" value="{{ $fuels[$inbound->fuel_type] ?? $inbound->fuel_type }}" disabled>
                </div>
                <div class="form-group">
                    <label>{{ __('tickets.spec') }}</label>
                    <input type="text" class="form-control" value="{{ config('ticket_specs.' . $inbound->spec) }} {{ __('tickets.kg') }}" disabled>
                    <small class="form-text text-muted">{{ __('tickets.ticket_inbound_fixed_hint') }}</small>
                </div>
                <div class="form-group">
                    <label for="f_quantity">{{ __('tickets.quantity') }}</label>
                    <input id="f_quantity" name="quantity" type="number" step="1" value="{{ old('quantity', $inbound->quantity) }}" class="form-control @error('quantity') is-invalid @enderror">
                    <small class="form-text text-muted">{{ __('tickets.quantity_hint') }}</small>
                    @error('quantity') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_cost">{{ __('tickets.cost') }}</label>
                    <input id="f_cost" name="cost" type="number" step="0.0001" min="0" value="{{ old('cost', $inbound->cost) }}" class="form-control @error('cost') is-invalid @enderror">
                    <small class="form-text text-muted">{{ __('tickets.cost_hint') }}</small>
                    @error('cost') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_inbound_date">{{ __('tickets.inbound_date') }}</label>
                    <input id="f_inbound_date" name="inbound_date" type="date" value="{{ old('inbound_date', substr((string) $inbound->inbound_date, 0, 10)) }}" class="form-control @error('inbound_date') is-invalid @enderror">
                    @error('inbound_date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_note">{{ __('tickets.note') }}</label>
                    <input id="f_note" name="note" type="text" value="{{ old('note', $inbound->note) }}" class="form-control @error('note') is-invalid @enderror">
                    @error('note') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('tickets.save') }}</button>
                <a href="{{ route('ticket-inbound.index') }}" class="btn btn-default">{{ __('tickets.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
