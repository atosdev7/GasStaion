@extends('layouts.app')

@section('title', __('menu.group_admin') . ' - ' . __('menu.go_live'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <p class="text-muted">{{ __('initialize.go_live_hint') }}</p>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.go_live') }}</h3></div>
        <form method="POST" action="{{ route('balances.golive.save') }}">
            @csrf
            <div class="card-body">
                <div class="form-group" style="max-width: 260px">
                    <label for="f_go_live">{{ __('initialize.go_live_date') }}</label>
                    <input id="f_go_live" name="go_live_date" type="date" value="{{ old('go_live_date', $goLive) }}" class="form-control" required>
                </div>
                @if ($goLive)
                    <div class="text-muted" id="go-live-state">{{ __('initialize.go_live_state', ['publishes' => $oldPublishes, 'rows' => $madeRows]) }}</div>
                @else
                    <div class="text-muted" id="go-live-state">{{ __('initialize.go_live_not_set') }}</div>
                @endif
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('initialize.save') }}</button>
            </div>
        </form>
    </div>
@endsection
