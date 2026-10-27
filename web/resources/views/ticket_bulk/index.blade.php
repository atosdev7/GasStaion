@extends('layouts.app')

@section('title', __('menu.ticket_bulk'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <p class="text-muted">{{ __('tickets.bulk_hint') }}</p>
    @unless ($goLive)
        <div class="alert alert-warning">{{ __('tickets.bulk_needs_go_live') }}</div>
    @endunless

    @isset($preview)
        {{-- the number of tickets the action will work on, and the button that does it --}}
        <div class="card card-warning" id="bulk-preview">
            <div class="card-header"><h3 class="card-title">{{ __('tickets.bulk_preview') }}</h3></div>
            <form method="POST" action="{{ route('ticket-bulk.run') }}">
                @csrf
                <input type="hidden" name="stock_item" value="{{ $preview['stock_item'] }}">
                <input type="hidden" name="ranges" value="{{ $preview['ranges'] }}">
                <input type="hidden" name="action" value="{{ $preview['action'] }}">
                <input type="hidden" name="confirm" value="1">
                <div class="card-body">
                    <p class="mb-0"><strong>{{ __('tickets.bulk_will_' . $preview['action'], ['count' => number_format($preview['count'])]) }}</strong></p>
                    <p class="text-muted mb-0">{{ $options[$preview['stock_item']] ?? '' }} / {{ $preview['ranges'] }}</p>
                </div>
                <div class="card-footer">
                    @if ($preview['count'] > 0)
                        <button type="submit" class="btn btn-danger" onclick="return confirm(@json(__('tickets.bulk_confirm')))">{{ __('tickets.bulk_do') }}</button>
                    @endif
                    <a href="{{ route('ticket-bulk.index') }}" class="btn btn-default">{{ __('tickets.bulk_cancel') }}</a>
                </div>
            </form>
        </div>
    @endisset

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('menu.ticket_bulk') }}</h3></div>
        <form method="POST" action="{{ route('ticket-bulk.run') }}">
            @csrf
            <div class="card-body">
                <div class="form-row">
                    <div class="col-md-3 form-group">
                        <label for="f_bulk_item">{{ __('tickets.bulk_stock_item') }}</label>
                        <select id="f_bulk_item" name="stock_item" class="form-control">
                            @foreach ($options as $key => $label)
                                <option value="{{ $key }}" {{ old('stock_item', $preview['stock_item'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5 form-group">
                        <label for="f_bulk_ranges">{{ __('tickets.bulk_ranges') }}</label>
                        <textarea id="f_bulk_ranges" name="ranges" rows="3" class="form-control" placeholder="1-150000, 150200-150500">{{ old('ranges', $preview['ranges'] ?? '') }}</textarea>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="f_bulk_action">{{ __('tickets.bulk_action') }}</label>
                        <select id="f_bulk_action" name="action" class="form-control">
                            @foreach (['delete', 'sold', 'restrict'] as $action)
                                <option value="{{ $action }}" {{ old('action', $preview['action'] ?? '') === $action ? 'selected' : '' }}>{{ __('tickets.bulk_action_' . $action) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('tickets.bulk_preview_button') }}</button>
            </div>
        </form>
    </div>
@endsection
