@if (!$ticket && !empty($legacy))
    {{-- no row, but inside a publish dated before go-live: sold on paper before go-live, and nothing on this system says it was used --}}
    <div class="alert alert-info">{{ __('tickets.ticket_legacy', ['publish' => $legacy->id, 'date' => substr((string) $legacy->date, 0, 10), 'start' => $legacy->start_serial, 'end' => $legacy->end_serial]) }}</div>
@elseif (!$ticket)
    <div class="alert alert-warning">{{ __('tickets.ticket_not_found') }}</div>
@else
    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('tickets.barcode') }}: {{ $ticket->barcode }}</h3></div>
        <div class="card-body">
            <table class="table table-sm">
                <tr>
                    <th style="width: 200px">{{ __('tickets.fuel_type') }}</th>
                    <td>{{ $fuels[$ticket->fuel_type] ?? $ticket->fuel_type }}</td>
                </tr>
                <tr>
                    <th>{{ __('tickets.spec') }}</th>
                    <td>
                        @if (!is_null($ticket->spec))
                            {{ config('ticket_specs.' . $ticket->spec) }} {{ __('tickets.kg') }}
                        @else
                            {{ __('tickets.amount_kg') }}: {{ number_format($ticket->amount_kg, 2) }} {{ __('tickets.kg') }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>{{ __('tickets.code') }}</th>
                    <td>{{ $ticket->code }}</td>
                </tr>
                <tr>
                    <th>{{ __('tickets.status') }}</th>
                    <td><span class="badge {{ $ticket->status === config('ticket_statuses.restrict') ? 'badge-danger' : 'badge-info' }}">{{ __('tickets.ticket_status_' . array_search($ticket->status, config('ticket_statuses'))) }}</span></td>
                </tr>
                @if ($ticket->print_count > 0)
                    <tr>
                        <th>{{ __('tickets.print_count') }}</th>
                        <td>{{ $ticket->print_count }} ({{ __('tickets.last_printed_at') }}: {{ $ticket->last_printed_at }})</td>
                    </tr>
                @endif
            </table>
        </div>
        @can('tickets.restrict')
            @if (in_array($ticket->status, [config('ticket_statuses.publish'), config('ticket_statuses.sale')], true))
                <div class="card-footer">
                    <form method="POST" action="{{ route('tickets.restrict', $ticket->barcode) }}" onsubmit="return confirm(@json(__('tickets.restrict_confirm')))">
                        @csrf
                        <input type="hidden" name="back" value="{{ $back }}">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> {{ __('tickets.restrict_ticket') }}</button>
                    </form>
                </div>
            @elseif ($ticket->status === config('ticket_statuses.restrict'))
                <div class="card-footer">
                    <form method="POST" action="{{ route('tickets.lift-restriction', $ticket->barcode) }}" onsubmit="return confirm(@json(__('tickets.lift_confirm')))">
                        @csrf
                        <input type="hidden" name="back" value="{{ $back }}">
                        <button type="submit" class="btn btn-warning"><i class="fas fa-undo"></i> {{ __('tickets.lift_restriction') }}</button>
                    </form>
                </div>
            @endif
        @endcan
    </div>

    @if ($ticket->publish_id)
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('tickets.publish') }}</h3></div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th style="width: 200px">{{ __('tickets.ticket_status_publish') }}</th>
                        <td>
                            #{{ $ticket->publish_id }}
                            @if ($ticket->batch)
                                — {{ $ticket->batch->date ?: substr((string) $ticket->publish_date, 0, 10) }}
                                ({{ $ticket->batch->start_serial }} - {{ $ticket->batch->end_serial }})
                                @can('ticket_stock.view')
                                    <a href="{{ route('ticket-publish.show', $ticket->publish_id) }}" class="ml-2"><i class="fas fa-external-link-alt"></i> {{ __('tickets.view_tickets') }}</a>
                                @endcan
                            @else
                                — {{ substr((string) $ticket->publish_date, 0, 10) }}
                            @endif
                        </td></tr>
                </table>
            </div>
        </div>
    @endif

    {{-- the sale of the ticket: only to those who may see that sale (the assistant: his own ticket sales) --}}
    @if ($ticket->sale && \App\Support\Sales\SaleAccess::canSee(auth()->user(), $ticket->sale))
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('menu.ticket_sales') }}</h3></div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th style="width: 200px">{{ __('tickets.sale_date') }}</th><td>{{ $ticket->sale->sale_date }}</td></tr>
                    <tr><th>{{ __('tickets.customer_id') }}</th><td>{{ $customers[$ticket->sale->customer_id] ?? $ticket->sale->customer_id }}</td></tr>
                    <tr><th>{{ __('tickets.sale_type') }}</th><td>{{ __('tickets.sale_type_' . $ticket->sale->sale_type) }}</td></tr>
                    <tr><th>{{ __('tickets.usage') }}</th><td>{{ __('tickets.usage_' . $ticket->sale->usage) }}</td></tr>
                    <tr><th>{{ __('tickets.payment_method') }}</th><td>{{ __('tickets.payment_' . $ticket->sale->payment_method) }}</td></tr>
                    <tr><th>{{ __('tickets.total_amount') }}</th><td>{{ number_format($ticket->sale->total_amount, 2) }}</td></tr>
                </table>
            </div>
        </div>
    @endif

    @if ($ticket->loanSettlement && auth()->user()->can('loans.view'))
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('menu.ticket_loans') }}</h3></div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th style="width: 200px">{{ __('tickets.settled_loan', ['kg' => number_format($ticket->loanSettlement->amount_kg, 2)]) }}</th>
                        <td>{{ $ticket->loanSettlement->settled_at }}</td></tr>
                    @if ($ticket->loanSettlement->loan)
                        <tr><th>{{ __('tickets.customer_id') }}</th>
                            <td>{{ $customers[$ticket->loanSettlement->loan->customer_id] ?? $ticket->loanSettlement->loan->customer_id }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    @elseif ($ticket->supplement)
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('menu.supplements') }}</h3></div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th style="width: 200px">{{ __('tickets.supplied_at') }}</th><td>{{ $ticket->supplement->supplied_at }}</td></tr>
                    <tr><th>{{ __('tickets.nozzle_id') }}</th>
                        <td>
                            @if ($ticket->supplement->nozzle)
                                {{ optional($ticket->supplement->device)->name ?? $ticket->supplement->device_id }} — #{{ $ticket->supplement->nozzle->number }}
                            @else
                                {{ $ticket->supplement->nozzle_id }}
                            @endif
                        </td></tr>
                    <tr><th>{{ __('tickets.out_amount_lt') }}</th><td>{{ number_format($ticket->supplement->out_amount_lt, 2) }}</td></tr>
                    <tr><th>{{ __('tickets.out_amount_kg') }}</th><td>{{ number_format($ticket->supplement->out_amount_kg, 2) }}</td></tr>
                </table>
            </div>
        </div>
    @elseif ($ticket->supply_date)
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('menu.supplements') }}</h3></div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th style="width: 200px">{{ __('tickets.status') }}</th><td>{{ $ticket->supply_date }}</td></tr>
                </table>
            </div>
        </div>
    @endif
@endif
