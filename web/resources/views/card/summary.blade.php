@extends('layouts.app')

@section('title', __('menu.group_cards') . ' - ' . __('menu.card_summary'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('card-summary.index') }}" class="form-inline">
                <label for="f_from" class="mr-2">{{ __('cards.from') }}</label>
                <input id="f_from" name="from" type="date" value="{{ $from }}" class="form-control mr-3">
                <label for="f_to" class="mr-2">{{ __('cards.to') }}</label>
                <input id="f_to" name="to" type="date" value="{{ $to }}" class="form-control mr-3">
                <button type="submit" class="btn btn-primary">{{ __('cards.show') }}</button>
                <a href="{{ route('card-summary.export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success ml-auto"><i class="fas fa-file-excel"></i> {{ __('cards.export_xls') }}</a>
            </form>
            @if ($errors->any())
                <div class="text-danger mt-2">{{ $errors->first() }}</div>
            @endif
            <small class="form-text text-muted">{{ __('cards.summary_hint') }}</small>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('cards.holder') }}</th>
                    <th>{{ __('cards.uid') }}</th>
                    <th>{{ __('cards.fuel_type') }}</th>
                    <th class="text-right">{{ __('cards.opening') }}</th>
                    <th class="text-right">{{ __('cards.topped_up_kg') }}</th>
                    <th class="text-right">{{ __('cards.topped_up_amount') }}</th>
                    <th class="text-right">{{ __('cards.used_kg') }}</th>
                    <th class="text-right">{{ __('cards.refunded_kg') }}</th>
                    <th class="text-right">{{ __('cards.closing') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['card']->holder_name }}</td>
                        <td>{{ $row['card']->uid }}</td>
                        <td>{{ $fuels[$row['card']->fuel_type] ?? $row['card']->fuel_type }}</td>
                        <td class="text-right">{{ number_format($row['opening'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['topped_kg'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['topped_amount'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['used_kg'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['refunded_kg'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['closing'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">{{ __('cards.no_data') }}</td></tr>
                @endforelse
                </tbody>
                @if (count($rows))
                    <tfoot>
                    @php($all = collect($rows))
                    @foreach ($fuels as $key => $name)
                        @php($mine = $all->filter(function ($row) use ($key) { return $row['card']->fuel_type === $key; }))
                        @if ($mine->isNotEmpty())
                            <tr class="font-weight-bold">
                                <td colspan="3">{{ __('cards.total') }} {{ $name }}</td>
                                <td class="text-right">{{ number_format($mine->sum('opening'), 2) }}</td>
                                <td class="text-right">{{ number_format($mine->sum('topped_kg'), 2) }}</td>
                                <td class="text-right">{{ number_format($mine->sum('topped_amount'), 2) }}</td>
                                <td class="text-right">{{ number_format($mine->sum('used_kg'), 2) }}</td>
                                <td class="text-right">{{ number_format($mine->sum('refunded_kg'), 2) }}</td>
                                <td class="text-right">{{ number_format($mine->sum('closing'), 2) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
