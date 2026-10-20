@extends('layouts.app')

@section('title', __('tickets.publish'))

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('ticket-publish.update', $batch->id) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-group">
                    <label>{{ __('tickets.fuel_type') }}</label>
                    <input type="text" class="form-control" value="{{ $fuels[$batch->fuel_type] ?? $batch->fuel_type }}, {{ config('ticket_specs.' . $batch->spec) }} {{ __('tickets.kg') }}, {{ __('tickets.code') }} {{ $batch->code }}" disabled>
                    <small class="form-text text-muted">{{ __('tickets.batch_edit_hint') }}</small>
                </div>
                <div class="form-group">
                    <label for="f_date">{{ __('tickets.date') }}</label>
                    <input id="f_date" name="date" type="date" value="{{ old('date', substr((string) $batch->date, 0, 10)) }}" class="form-control @error('date') is-invalid @enderror">
                    @error('date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_start_serial">{{ __('tickets.start_serial') }}</label>
                    <input id="f_start_serial" name="start_serial" type="number" step="1" min="1" value="{{ old('start_serial', $batch->start_serial) }}" class="form-control @error('start_serial') is-invalid @enderror">
                    @error('start_serial') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_end_serial">{{ __('tickets.end_serial') }}</label>
                    <input id="f_end_serial" name="end_serial" type="number" step="1" min="1" value="{{ old('end_serial', $batch->end_serial) }}" class="form-control @error('end_serial') is-invalid @enderror">
                    @error('end_serial') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('tickets.save') }}</button>
                <a href="{{ route('ticket-publish.index') }}" class="btn btn-default">{{ __('tickets.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
