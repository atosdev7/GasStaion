@extends('layouts.app')

@section('title', __('cards.history_of', ['name' => $card->holder_name]))

@section('content')
    <div class="row">
        <div class="col-md-3 col-6">
            <div class="info-box"><div class="info-box-content">
                <span class="info-box-text">{{ __('cards.balance') }}</span>
                <span class="info-box-number">{{ number_format($balance, 2) }}</span>
            </div></div>
        </div>
        <div class="col-md-5 col-6">
            <div class="info-box"><div class="info-box-content">
                <span class="info-box-text">{{ $card->holder_name }} &middot; {{ $card->uid }}</span>
                <span class="info-box-number">{{ $fuels[$card->fuel_type] ?? $card->fuel_type }}@if ($card->free_payment) <span class="badge badge-info">{{ __('cards.free_payment') }}</span>@endif</span>
            </div></div>
        </div>
    </div>

    @if (abs($balance - $recordsBalance) > 0.004)
        <div class="alert alert-warning">{{ __('cards.ledger_differs', ['ledger' => number_format($balance, 2), 'records' => number_format($recordsBalance, 2)]) }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('cards.history', $card->id) }}" class="form-inline">
                <label for="f_from" class="mr-2">{{ __('cards.from') }}</label>
                <input id="f_from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="form-control mr-3">
                <label for="f_to" class="mr-2">{{ __('cards.to') }}</label>
                <input id="f_to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="form-control mr-3">
                <button type="submit" class="btn btn-primary">{{ __('cards.show') }}</button>
                <a href="{{ route('cards.index') }}" class="btn btn-default ml-2">{{ __('cards.back_to_cards') }}</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('cards.history') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('cards.when') }}</th>
                    <th>{{ __('cards.entry_type') }}</th>
                    <th class="text-right">{{ __('cards.kg_in_out') }}</th>
                    <th class="text-right">{{ __('cards.balance_after') }}</th>
                    <th class="text-right">{{ __('cards.price') }}</th>
                    <th class="text-right">{{ __('cards.total_amount') }}</th>
                    <th>{{ __('cards.user') }}</th>
                    <th>{{ __('cards.comment') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr class="{{ $entry->type === 'reversal' ? 'text-muted' : '' }}">
                        <td>{{ $entry->occurred_at }}</td>
                        <td>{{ __('cards.entry_' . $entry->type) }}</td>
                        <td class="text-right {{ $entry->kg < 0 ? 'text-danger' : 'text-success' }}">{{ $entry->kg > 0 ? '+' : '' }}{{ number_format($entry->kg, 2) }}</td>
                        <td class="text-right">{{ number_format($entry->balance_kg, 2) }}</td>
                        <td class="text-right">{{ $entry->price !== null ? number_format($entry->price, 2) : '' }}</td>
                        <td class="text-right">{{ $entry->total_amount !== null ? number_format($entry->total_amount, 2) : '' }}</td>
                        <td>{{ $entry->user_id ? ($users[$entry->user_id] ?? $entry->user_id) : '' }}</td>
                        <td>{{ $entry->comment }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('cards.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <div class="text-muted mb-2">{{ __('cards.history_hint') }}</div>
            {{ $entries->links() }}
        </div>
    </div>
@endsection
