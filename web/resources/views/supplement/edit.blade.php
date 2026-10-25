@extends('layouts.app')

@section('title', __('supplements.edit_title'))

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('supplements.update', $supplement->id) }}">
            @csrf
            @method('PUT')
            <div class="card-header"><h3 class="card-title">{{ __('supplements.supply_date') }}: {{ $supplement->supply_day }} &middot; {{ $fuels[$supplement->fuel_type] ?? $supplement->fuel_type }}</h3></div>
            <div class="card-body">
                <p class="text-muted">{{ $full ? __('supplements.edit_hint_full') : __('supplements.edit_hint_comment') }}</p>

                <table class="table table-sm w-auto mb-4">
                    <tr><th style="width: 220px">{{ __('supplements.preset_kg') }}</th><td>{{ number_format($supplement->preset_kg, 2) }} {{ __('supplements.kg') }}</td></tr>
                    <tr><th>{{ __('supplements.density') }}</th><td>{{ $supplement->density !== null ? rtrim(rtrim(number_format($supplement->density, 4, '.', ''), '0'), '.') : '-' }}</td></tr>
                    <tr><th>{{ __('supplements.out_amount') }}</th><td>{{ number_format($supplement->out_amount_kg, 2) }} {{ __('supplements.kg') }} / {{ number_format($supplement->out_amount_lt, 2) }} {{ __('supplements.liter') }}</td></tr>
                    @if ($supplement->remainder)
                        <tr><th>{{ __('supplements.remainder') }}</th><td>{{ $supplement->remainder->ticket_barcode }} ({{ number_format($supplement->remainder->amount_kg, 2) }} {{ __('supplements.kg') }})</td></tr>
                    @endif
                </table>

                @if ($full)
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="f_nozzle_id">{{ __('supplements.nozzle_id') }}</label>
                            <select id="f_nozzle_id" name="nozzle_id" class="form-control @error('nozzle_id') is-invalid @enderror">
                                @foreach ($nozzleOptions as $id => $label)
                                    <option value="{{ $id }}" {{ (string) old('nozzle_id', $supplement->nozzle_id) === (string) $id ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('nozzle_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group col-md-4">
                            <label for="f_supplied_at">{{ __('supplements.supplied_at') }}</label>
                            <input id="f_supplied_at" name="supplied_at" type="text" value="{{ old('supplied_at', substr((string) $supplement->supplied_at, 0, 19)) }}" class="form-control @error('supplied_at') is-invalid @enderror">
                            @error('supplied_at') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group col-md-4">
                            <label for="f_mode">{{ __('supplements.mode') }}</label>
                            <select id="f_mode" name="mode" class="form-control @error('mode') is-invalid @enderror">
                                @foreach (\App\Models\Supplements\Supplement::MODES as $mode)
                                    <option value="{{ $mode }}" {{ old('mode', $supplement->mode) === $mode ? 'selected' : '' }}>{{ __('supplements.mode_' . $mode) }}</option>
                                @endforeach
                            </select>
                            @error('mode') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="f_out_amount_lt">{{ __('supplements.out_amount_lt') }}</label>
                            <input id="f_out_amount_lt" name="out_amount_lt" type="number" step="0.01" min="0.01" value="{{ old('out_amount_lt', $supplement->out_amount_lt) }}"
                                   {{ $litresFixed ? 'readonly' : '' }} class="form-control @error('out_amount_lt') is-invalid @enderror">
                            @if ($litresFixed)
                                <small class="form-text text-muted">{{ __('supplements.litres_fixed') }}</small>
                            @endif
                            @error('out_amount_lt') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                @endif

                <div class="form-group">
                    <label for="f_comment">{{ __('supplements.comment') }}</label>
                    <input id="f_comment" name="comment" type="text" maxlength="255" value="{{ old('comment', $supplement->comment) }}" class="form-control @error('comment') is-invalid @enderror">
                    @error('comment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('supplements.save') }}</button>
                <a href="{{ route('supplements.index') }}" class="btn btn-default">{{ __('supplements.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
