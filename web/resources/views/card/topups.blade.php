@extends('layouts.app')

@section('title', __('menu.card_topups'))

@section('actions')
    @can('cards.topup')
        <a href="{{ route('card-topups.create') }}" class="btn btn-success"><i class="fas fa-plus-circle"></i> {{ __('cards.top_up') }}</a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-weight-hanging"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('cards.topups_total_kg') }}</span>
                    <span class="info-box-number">{{ number_format($totalKg, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('menu.card_topups') }}</h3></div>
        @if ($errors->any())
            <div class="card-body pb-0 text-danger">{{ $errors->first() }}</div>
        @endif
        <form method="GET" action="{{ route('card-topups.index') }}" id="topup-filters"></form>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('cards.sale_date') }}</th>
                    <th>{{ __('cards.time') }}</th>
                    <th>{{ __('cards.holder') }}</th>
                    <th>{{ __('cards.uid') }}</th>
                    <th>{{ __('cards.fuel_type') }}</th>
                    <th class="text-right">{{ __('cards.amount_kg') }}</th>
                    <th class="text-right">{{ __('cards.price') }}</th>
                    <th class="text-right">{{ __('cards.total_amount') }}</th>
                    <th>{{ __('cards.by') }}</th>
                </tr>
                <tr class="filters">
                    <th><input name="sale_date" form="topup-filters" type="date" value="{{ $filters['sale_date'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('topup-filters').submit()"></th>
                    <th></th>
                    <th colspan="2">
                        <select name="card" form="topup-filters" id="topup-card-filter" class="form-control form-control-sm js-select2" data-allow-clear style="min-width: 260px">
                            <option value="">{{ __('cards.all_cards') }}</option>
                            @foreach ($cards as $id => $card)
                                <option value="{{ $id }}" {{ ($filters['card'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $card->label }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th colspan="4"></th>
                    <th class="text-right text-nowrap">
                        <button type="submit" form="topup-filters" class="btn btn-sm btn-primary">{{ __('cards.show') }}</button>
                        <a href="{{ route('card-topups.index') }}" class="btn btn-sm btn-default">{{ __('cards.clear_filters') }}</a>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($topUps as $sale)
                    @php($card = $cards->get($sale->card_id))
                    <tr>
                        <td>{{ $sale->sale_date }}</td>
                        <td>{{ $sale->created_at ? $sale->created_at->format('H:i') : '' }}</td>
                        <td>{{ $card ? $card->holder_name : '' }}</td>
                        <td>{{ $card ? $card->uid : '' }}</td>
                        <td>{{ $fuels[$sale->fuel_type] ?? $sale->fuel_type }}</td>
                        <td class="text-right">{{ number_format($sale->amount_kg, 2) }}</td>
                        <td class="text-right">{{ number_format($sale->price, 2) }}</td>
                        <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
                        <td>{{ $users[$sale->user_id] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">{{ __('cards.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $topUps->links() }}</div>
    </div>
@endsection

@push('scripts')
    <script>
        $('#topup-card-filter').on('change', function () { document.getElementById('topup-filters').submit(); });
    </script>
@endpush
