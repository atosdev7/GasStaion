@extends('layouts.app')

@section('title', __('menu.group_confirmations') . ' - ' . __('menu.confirmation_tickets'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @include('confirmation._header', ['route' => 'confirmations.tickets', 'area' => 'tickets'])

    {{-- The blank ticket stock of the date: the ticket inbounds (a negative quantity takes blank tickets out) and the publishes. The confirm button confirms the
         ones checked that are not confirmed yet; Unconfirm (a manager) sends the same form to its own address (formaction). --}}
    <form method="POST" action="{{ route('confirmations.store') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="from" value="tickets">

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('confirm.ticket_inbounds') }} ({{ $inbounds->count() }})</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th style="width: 30px"><input type="checkbox" class="check-all" data-target="check-ticket-inbounds" title="{{ __('confirm.check_all') }}"></th>
                        <th>{{ __('confirm.fuel_type') }}</th>
                        <th class="text-right">{{ __('confirm.spec') }}</th>
                        <th class="text-right">{{ __('confirm.quantity') }}</th>
                        <th class="text-right">{{ __('confirm.cost') }}</th>
                        <th>{{ __('confirm.note') }}</th>
                        <th class="text-right">{{ __('confirm.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($inbounds as $row)
                        <tr>
                            <td>
                                @if ($row->isConfirmed())
                                    <i class="fas fa-check-circle text-success" title="{{ __('confirm.confirmed') }}"></i><span class="sr-only">{{ __('confirm.confirmed') }}</span>
                                @else
                                    <input type="checkbox" name="ticket_inbounds[]" value="{{ $row->id }}" class="check-ticket-inbounds">
                                @endif
                            </td>
                            <td>{{ $fuels[$row->fuel_type] ?? $row->fuel_type }}</td>
                            <td class="text-right">{{ $specs[$row->spec] ?? $row->spec }} {{ __('confirm.kg') }}</td>
                            <td class="text-right">{{ $row->quantity }}</td>
                            <td class="text-right">{{ $row->cost !== null ? number_format($row->cost, 4) : '' }}</td>
                            <td>{{ $row->note }}</td>
                            <td class="text-right">
                                @if ($row->isConfirmed())
                                    @can('confirmations.unconfirm')
                                        <button type="submit" formmethod="post" formaction="{{ route('ticket-inbound.unconfirm', $row->id) }}" class="btn btn-xs btn-outline-secondary"
                                                onclick="return confirm(@json(__('confirm.unconfirm_confirm')))"><i class="fas fa-undo fa-fw"></i> {{ __('confirm.unconfirm') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">{{ __('confirm.nothing_here') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if ($inbounds->isNotEmpty())
                        <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="3">{{ __('confirm.total') }}</td>
                            <td class="text-right">{{ $inbounds->sum('quantity') }}</td>
                            <td colspan="3"></td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('confirm.ticket_publishes') }} ({{ $publishes->count() }})</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th style="width: 30px"><input type="checkbox" class="check-all" data-target="check-ticket-publishes" title="{{ __('confirm.check_all') }}"></th>
                        <th>#</th>
                        <th>{{ __('confirm.fuel_type') }}</th>
                        <th class="text-right">{{ __('confirm.spec') }}</th>
                        <th>{{ __('confirm.code') }}</th>
                        <th class="text-right">{{ __('confirm.serials') }}</th>
                        <th class="text-right">{{ __('confirm.quantity') }}</th>
                        <th class="text-right">{{ __('confirm.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($publishes as $row)
                        <tr>
                            <td>
                                @if ($row->isConfirmed())
                                    <i class="fas fa-check-circle text-success" title="{{ __('confirm.confirmed') }}"></i><span class="sr-only">{{ __('confirm.confirmed') }}</span>
                                @else
                                    <input type="checkbox" name="ticket_publishes[]" value="{{ $row->id }}" class="check-ticket-publishes">
                                @endif
                            </td>
                            <td>{{ $row->id }}</td>
                            <td>{{ $fuels[$row->fuel_type] ?? $row->fuel_type }}</td>
                            <td class="text-right">{{ $specs[$row->spec] ?? $row->spec }} {{ __('confirm.kg') }}</td>
                            <td>{{ $row->code }}</td>
                            <td class="text-right">{{ $row->start_serial }} - {{ $row->end_serial }}</td>
                            <td class="text-right">{{ $row->count }}</td>
                            <td class="text-right">
                                @if ($row->isConfirmed())
                                    @can('confirmations.unconfirm')
                                        <button type="submit" formmethod="post" formaction="{{ route('ticket-publish.unconfirm', $row->id) }}" class="btn btn-xs btn-outline-secondary"
                                                onclick="return confirm(@json(__('confirm.unconfirm_confirm')))"><i class="fas fa-undo fa-fw"></i> {{ __('confirm.unconfirm') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">{{ __('confirm.nothing_here') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if ($publishes->isNotEmpty())
                        <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="6">{{ __('confirm.total') }}</td>
                            <td class="text-right">{{ $publishes->sum('count') }}</td>
                            <td></td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        @if ($inbounds->contains(function ($row) { return !$row->isConfirmed(); }) || $publishes->contains(function ($row) { return !$row->isConfirmed(); }))
            <button type="submit" class="btn btn-success" onclick="return confirm(@json(__('confirm.confirm_question', ['date' => $date])))">
                <i class="fas fa-check-double"></i> {{ __('confirm.confirm') }}
            </button>
            <small class="text-muted ml-2">{{ __('confirm.confirm_hint') }}</small>
        @endif
    </form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.check-all').forEach(function (box) {
    box.addEventListener('change', function () {
        document.querySelectorAll('.' + box.getAttribute('data-target')).forEach(function (el) { el.checked = box.checked; });
    });
});
</script>
@endpush
