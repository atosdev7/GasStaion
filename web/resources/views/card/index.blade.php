@extends('layouts.app')

@section('title', __('menu.cards'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('menu.cards') }}</h3></div>
        {{-- every filter sits in the head of its own column; the inputs belong to this form by their form attribute --}}
        <form method="GET" action="{{ route('cards.index') }}" id="card-filters"></form>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('cards.holder') }}</th>
                    <th>{{ __('cards.phone') }}</th>
                    <th>{{ __('cards.uid') }}</th>
                    <th>{{ __('cards.fuel_type') }}</th>
                    <th class="text-right">{{ __('cards.balance') }}</th>
                    <th>{{ __('cards.status') }}</th>
                    <th>{{ __('cards.comment') }}</th>
                    <th class="text-right">{{ __('cards.actions') }}</th>
                </tr>
                <tr class="filters">
                    <th colspan="3"><input name="q" form="card-filters" type="text" maxlength="100" placeholder="{{ __('cards.search') }}" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" style="min-width: 220px"></th>
                    <th>
                        <select name="fuel_type" form="card-filters" class="form-control form-control-sm" onchange="document.getElementById('card-filters').submit()">
                            <option value="">{{ __('cards.all_fuel_types') }}</option>
                            @foreach ($fuels as $key => $name)
                                <option value="{{ $key }}" {{ ($filters['fuel_type'] ?? '') === (string) $key ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th></th>
                    <th>
                        <select name="status" form="card-filters" class="form-control form-control-sm" onchange="document.getElementById('card-filters').submit()">
                            <option value="">{{ __('cards.all_statuses') }}</option>
                            @foreach (App\Models\Card::STATUSES as $status)
                                <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ __('cards.status_' . $status) }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th></th>
                    <th class="text-right text-nowrap">
                        <button type="submit" form="card-filters" class="btn btn-sm btn-primary">{{ __('cards.show') }}</button>
                        <a href="{{ route('cards.index') }}" class="btn btn-sm btn-default">{{ __('cards.clear_filters') }}</a>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($cards as $card)
                    <tr>
                        <td>{{ $card->holder_name }}</td>
                        <td>{{ $card->holder_phone }}</td>
                        <td>{{ $card->uid }}</td>
                        <td>{{ $fuels[$card->fuel_type] ?? $card->fuel_type }}</td>
                        <td class="text-right"><a href="{{ route('cards.history', $card->id) }}" title="{{ __('cards.history') }}">{{ number_format($balances[$card->id] ?? 0, 2) }}</a></td>
                        <td>
                            <span class="badge {{ ['active' => 'badge-success', 'locked' => 'badge-warning', 'blocked' => 'badge-danger'][$card->status] ?? 'badge-secondary' }}">{{ __('cards.status_' . $card->status) }}</span>
                            @if ($card->status === 'locked')
                                <small class="text-muted">{{ __('cards.tries', ['n' => $card->failed_attempts]) }}</small>
                            @endif
                        </td>
                        <td>{{ $card->comment }}</td>
                        <td class="text-nowrap text-right">
                            @canany(['cards.manage', 'cards.topup'])
                                <div class="btn-group card-actions">
                                    <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">{{ __('cards.actions') }}</button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        @can('cards.topup')
                                            @if ($card->status !== 'blocked')
                                                <a href="{{ route('card-topups.create', ['card' => $card->id]) }}" class="dropdown-item"><i class="fas fa-plus-circle fa-fw"></i> {{ __('cards.top_up') }}</a>
                                            @endif
                                        @endcan
                                        @can('cards.manage')
                                            <a href="{{ route('cards.edit', $card->id) }}" class="dropdown-item"><i class="fas fa-edit fa-fw"></i> {{ __('cards.edit_button') }}</a>
                                            @if ($card->status === 'locked')
                                                <form method="POST" action="{{ route('cards.unlock', $card->id) }}">@csrf
                                                    <button type="submit" class="dropdown-item"><i class="fas fa-unlock fa-fw"></i> {{ __('cards.unlock') }}</button>
                                                </form>
                                            @endif
                                            <a href="{{ route('cards.pin', $card->id) }}" class="dropdown-item"><i class="fas fa-key fa-fw"></i> {{ __('cards.reset_pin') }}</a>
                                            @if ($card->status === 'blocked')
                                                <form method="POST" action="{{ route('cards.unblock', $card->id) }}">@csrf
                                                    <button type="submit" class="dropdown-item"><i class="fas fa-check-circle fa-fw"></i> {{ __('cards.unblock') }}</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('cards.block', $card->id) }}" onsubmit="return confirm(@json(__('cards.block_confirm')))">@csrf
                                                    <button type="submit" class="dropdown-item text-danger"><i class="fas fa-ban fa-fw"></i> {{ __('cards.block') }}</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </div>
                            @endcanany
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('cards.no_cards') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $cards->links() }}</div>
    </div>
@endsection

@push('scripts')
    <script>
        // The menu of the actions is fixed to the window (the table scrolls sideways and would cut it) and sits under its button.
        $(document).on('shown.bs.dropdown', '.card-actions', function () {
            var button = $(this).find('.dropdown-toggle')[0].getBoundingClientRect();
            var menu = $(this).find('.dropdown-menu');
            menu.css({position: 'fixed', top: button.bottom + 'px', left: Math.max(4, button.right - menu.outerWidth()) + 'px', right: 'auto', transform: 'none'});
        });
        $(window).on('scroll resize', function () { $('.card-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });
        $('.table-responsive').on('scroll', function () { $('.card-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });
    </script>
@endpush
