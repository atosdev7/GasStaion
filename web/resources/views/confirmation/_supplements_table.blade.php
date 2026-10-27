{{-- The supplements of the page: $byFuel (fuel key => supplements), one table for each fuel type, one under the other (the first fuel type on top), under a heading each. --}}
{{-- the cost per kg of a supply is one more column for a user who may see costs; a cost with no fuel behind it (the layer of that cost in its tank is below zero) is marked --}}
@php($showCost = auth()->user()->can('costs.view'))
@php($cols = $showCost ? 10 : 9)
@foreach ($byFuel as $fuel => $rows)
    <h5 class="mt-3 mb-2 supplement-fuel-{{ $fuel }}">{{ $fuels[$fuel] ?? $fuel }} ({{ $rows->count() }})</h5>
    <div class="table-responsive">
        <table class="table table-sm table-hover text-nowrap" id="supplements-table-{{ $fuel }}">
            <thead>
            <tr>
                <th style="width: 30px"><input type="checkbox" class="check-all" data-target="check-supplements-{{ $fuel }}" title="{{ __('confirm.check_all') }}"></th>
                <th>{{ __('confirm.supplied_at') }}</th>
                <th>{{ __('confirm.nozzle_id') }}</th>
                <th>{{ __('confirm.mode') }}</th>
                <th class="text-right">{{ __('confirm.preset_amount') }}</th>
                <th class="text-right">{{ __('confirm.out_amount_kg') }}</th>
                @if ($showCost)
                    <th class="text-right">{{ __('confirm.supply_cost') }}</th>
                @endif
                <th>{{ __('confirm.remainder') }}</th>
                <th>{{ __('confirm.comment') }}</th>
                <th class="text-right">{{ __('confirm.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $supplement)
                <tr>
                    <td>
                        @if ($supplement->confirmed_at)
                            <i class="fas fa-check-circle text-success" title="{{ __('confirm.confirmed') }}"></i><span class="sr-only">{{ __('confirm.confirmed') }}</span>
                        @else
                            <input type="checkbox" name="supplements[]" value="{{ $supplement->id }}" class="check-supplements-{{ $fuel }}">
                        @endif
                    </td>
                    <td>{{ $supplement->supplied_at }}</td>
                    <td>{{ $nozzleLabels[$supplement->nozzle_id] ?? $supplement->nozzle_id }}</td>
                    <td>{{ __('confirm.mode_' . $supplement->mode) }}</td>
                    <td class="text-right">{{ number_format($supplement->preset_kg, 2) }}</td>
                    <td class="text-right">{{ number_format($supplement->out_amount_kg, 2) }}</td>
                    @if ($showCost)
                        <td class="text-right supplement-cost">
                            {{ $supplement->cost !== null ? number_format($supplement->cost, 4) : '-' }}
                            @if (isset($belowZero[$supplement->id]))
                                <i class="fas fa-exclamation-triangle text-warning" title="{{ __('confirm.cost_no_fuel') }}"></i>
                            @endif
                        </td>
                    @endif
                    <td>
                        @if ($supplement->remainder)
                            {{ $supplement->remainder->ticket_barcode }} ({{ number_format($supplement->remainder->amount_kg, 2) }} {{ __('confirm.kg') }})
                        @endif
                    </td>
                    <td>{{ $supplement->comment }}</td>
                    <td class="text-right">@include('confirmation._actions', ['kind' => 'supplement', 'record' => $supplement])</td>
                </tr>
            @empty
                <tr><td colspan="{{ $cols }}" class="text-muted">{{ __('confirm.nothing_here') }}</td></tr>
            @endforelse
            </tbody>
            @if ($rows->count() > 0)
                <tfoot>
                <tr class="font-weight-bold">
                    <td colspan="4">{{ __('confirm.total') }}</td>
                    <td class="text-right">{{ number_format($rows->sum('preset_kg'), 2) }}</td>
                    <td class="text-right">{{ number_format($rows->sum('out_amount_kg'), 2) }}</td>
                    @if ($showCost)
                        <td></td>
                    @endif
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endforeach
