@extends('layouts.app')

@section('title', __('menu.card_sales'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('menu.card_sales') }}</h3></div>
        <form method="GET" action="{{ route('card-sales.index') }}" id="card-sale-filters"></form>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('sales.sale_date') }}</th>
                    <th>{{ __('sales.holder') }}</th>
                    <th>{{ __('sales.uid') }}</th>
                    <th>{{ __('sales.fuel_type') }}</th>
                    <th class="text-right">{{ __('sales.amount_kg') }}</th>
                    <th class="text-right">{{ __('sales.price') }}</th>
                    <th class="text-right">{{ __('sales.total_amount') }}</th>
                    <th>{{ __('sales.by') }}</th>
                    <th class="text-right">{{ __('sales.actions') }}</th>
                </tr>
                <tr class="filters">
                    <th><input name="sale_date" form="card-sale-filters" type="date" value="{{ $filters['sale_date'] ?? '' }}" class="form-control form-control-sm" style="min-width: 150px" onchange="document.getElementById('card-sale-filters').submit()"></th>
                    <th colspan="2">
                        <select name="card" form="card-sale-filters" id="card-sale-card-filter" class="form-control form-control-sm js-select2" data-allow-clear style="min-width: 260px">
                            <option value="">{{ __('sales.all_cards') }}</option>
                            @foreach ($cards as $id => $card)
                                <option value="{{ $id }}" {{ ($filters['card'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $card->label }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th colspan="5"></th>
                    <th class="text-right text-nowrap">
                        <button type="submit" form="card-sale-filters" class="btn btn-sm btn-primary">{{ __('sales.show') }}</button>
                        <a href="{{ route('card-sales.index') }}" class="btn btn-sm btn-default">{{ __('sales.clear_filters') }}</a>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($sales as $s)
                    @php($card = $cards->get($s->card_id))
                    <tr>
                        <td>{{ $s->sale_date }}</td>
                        <td>{{ $card ? $card->holder_name : '' }}</td>
                        <td>{{ $card ? $card->uid : '' }}</td>
                        <td>{{ $fuels[$s->fuel_type] ?? $s->fuel_type }}</td>
                        <td class="text-right">{{ number_format($s->amount_kg, 2) }}</td>
                        <td class="text-right">{{ number_format($s->price, 2) }}</td>
                        <td class="text-right">{{ number_format($s->total_amount, 2) }}</td>
                        <td>{{ $users[$s->user_id] ?? '' }}</td>
                        <td class="text-nowrap text-right">
                            <div class="btn-group card-sale-actions">
                                <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">{{ __('sales.actions') }}</button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a href="{{ route('ticket-sales.show', $s->id) }}" class="dropdown-item"><i class="fas fa-eye fa-fw"></i> {{ __('sales.sale_view') }}</a>
                                    @can('card_sales.edit')
                                        @if ($s->confirmed_at)
                                            <span class="dropdown-item text-muted" title="{{ __('sale_edit.locked') }}"><i class="fas fa-lock fa-fw"></i> {{ __('sales.edit') }}</span>
                                        @else
                                            <a href="{{ route('ticket-sales.edit', $s->id) }}" class="dropdown-item"><i class="fas fa-edit fa-fw"></i> {{ __('sales.edit') }}</a>
                                        @endif
                                    @endcan
                                    @if ($blockers->has($s->id))
                                        @if ($blockers[$s->id] === null)
                                            <form method="POST" action="{{ route('card-sales.destroy', $s->id) }}" onsubmit="return confirm(@json(__('sales.cards_delete_confirm')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash fa-fw"></i> {{ __('sales.delete') }}</button>
                                            </form>
                                        @else
                                            <span class="dropdown-item text-muted" title="{{ __('sales.blocked_' . $blockers[$s->id]) }}"><i class="fas fa-lock fa-fw"></i> {{ __('sales.delete') }}</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">{{ __('sales.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $sales->links() }}</div>
    </div>
@endsection

@push('scripts')
    <script>
        $('#card-sale-card-filter').on('change', function () { document.getElementById('card-sale-filters').submit(); });

        $(document).on('shown.bs.dropdown', '.card-sale-actions', function () {
            var button = $(this).find('.dropdown-toggle')[0].getBoundingClientRect();
            var menu = $(this).find('.dropdown-menu');
            menu.css({position: 'fixed', top: button.bottom + 'px', left: Math.max(4, button.right - menu.outerWidth()) + 'px', right: 'auto', transform: 'none'});
        });
        $(window).on('scroll resize', function () { $('.card-sale-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });
        $('.table-responsive').on('scroll', function () { $('.card-sale-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });
    </script>
@endpush
