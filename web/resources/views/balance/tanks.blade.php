@extends('layouts.app')

@section('title', __('menu.group_init') . ' - ' . __('menu.tanks'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('initialize.opening_tank_stock') }}</h3></div>
        <form method="POST" action="{{ route('balances.tanks') }}">
            @csrf
            <div class="card-body table-responsive p-0">
                <table class="table text-nowrap">
                    <thead>
                    <tr>
                        <th>{{ __('initialize.tank_id') }}</th>
                        <th>{{ __('initialize.fuel_type') }}</th>
                        <th style="width: 220px">{{ __('initialize.amount_kg') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($tanks as $tank)
                        <tr>
                            <td>{{ $tank->name }}</td>
                            <td>{{ $fuels[$tank->fuel_type] ?? $tank->fuel_type }}</td>
                            <td>
                                <input name="tanks[{{ $tank->id }}]" type="number" step="0.01" min="0"
                                       value="{{ old('tanks.' . $tank->id, $tankBalances[$tank->id] ?? '') }}"
                                       class="form-control form-control-sm" aria-label="{{ $tank->name }}">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __('initialize.save') }}</button>
                <small class="text-muted ml-2">{{ __('initialize.opening_tank_hint') }}</small>
            </div>
        </form>
    </div>
@endsection
