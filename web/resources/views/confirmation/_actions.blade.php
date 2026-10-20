{{-- The actions of one record on the daily confirmation: $kind 'sale' or 'supplement', $record. View opens the record in a popup (the same detail as
     on its list); a sale can be edited (date, comment, receipt) until it is confirmed; a manager can take a confirmation back. The tables sit
     inside the confirm form, so Unconfirm is a button that sends the page's form to its own address (formaction). --}}
<div class="btn-group confirm-actions">
    <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">{{ __('confirm.actions') }}</button>
    <div class="dropdown-menu dropdown-menu-right">
        @if ($kind === 'sale')
            <a href="{{ route('ticket-sales.show', $record->id) }}" class="dropdown-item record-detail"><i class="fas fa-eye fa-fw"></i> {{ __('confirm.sale_view') }}</a>
            @if (auth()->user()->can($record->sale_type === 'card' ? 'card_sales.edit' : 'sales.edit'))
                @if ($record->confirmed_at)
                    <span class="dropdown-item text-muted" title="{{ __('confirm.locked') }}"><i class="fas fa-lock fa-fw"></i> {{ __('confirm.edit') }}</span>
                @else
                    <a href="{{ route('ticket-sales.edit', $record->id) }}" class="dropdown-item"><i class="fas fa-edit fa-fw"></i> {{ __('confirm.edit') }}</a>
                @endif
            @endif
        @else
            <a href="{{ route('supplements.show', $record->id) }}" class="dropdown-item record-detail"><i class="fas fa-eye fa-fw"></i> {{ __('confirm.sale_view') }}</a>
        @endif
        @if ($record->confirmed_at)
            @can('confirmations.unconfirm')
                <button type="submit" formmethod="post" formaction="{{ route($kind === 'sale' ? 'ticket-sales.unconfirm' : 'supplements.unconfirm', $record->id) }}" class="dropdown-item"
                        onclick="return confirm(@json(__('confirm.unconfirm_confirm')))"><i class="fas fa-undo fa-fw"></i> {{ __('confirm.unconfirm') }}</button>
            @endcan
        @endif
    </div>
</div>
