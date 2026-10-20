{{-- The supplements of the page as ONE table: $byFuel (fuel key => supplements), the fuel types side by side (the first on the left, the
     second on the right) under a heading each; row n holds the n-th supplement of every fuel type. --}}
@php($lines = max(array_map(function ($rows) { return $rows->count(); }, $byFuel) ?: [0]))
<div class="table-responsive">
    <table class="table table-sm table-hover text-nowrap">
        <thead>
        <tr>
            @foreach ($byFuel as $fuel => $rows)
                <th colspan="9" class="text-center {{ $loop->first ? '' : 'border-left' }}">{{ $fuels[$fuel] ?? $fuel }} ({{ $rows->count() }})</th>
            @endforeach
        </tr>
        <tr>
            @foreach ($byFuel as $fuel => $rows)
                <th style="width: 30px" class="{{ $loop->first ? '' : 'border-left' }}"><input type="checkbox" class="check-all" data-target="check-supplements-{{ $fuel }}" title="{{ __('confirm.check_all') }}"></th>
                <th>{{ __('confirm.supplied_at') }}</th>
                <th>{{ __('confirm.nozzle_id') }}</th>
                <th>{{ __('confirm.mode') }}</th>
                <th class="text-right">{{ __('confirm.preset_amount') }}</th>
                <th class="text-right">{{ __('confirm.out_amount_kg') }}</th>
                <th>{{ __('confirm.remainder') }}</th>
                <th>{{ __('confirm.comment') }}</th>
                <th class="text-right">{{ __('confirm.actions') }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @for ($i = 0; $i < $lines; $i++)
            <tr>
                @foreach ($byFuel as $fuel => $rows)
                    @php($supplement = $rows->get($i))
                    @if ($supplement)
                        <td class="{{ $loop->first ? '' : 'border-left' }}">
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
                        <td>
                            @if ($supplement->remainder)
                                {{ $supplement->remainder->ticket_barcode }} ({{ number_format($supplement->remainder->amount_kg, 2) }} {{ __('confirm.kg') }})
                            @endif
                        </td>
                        <td>{{ $supplement->comment }}</td>
                        <td class="text-right">@include('confirmation._actions', ['kind' => 'supplement', 'record' => $supplement])</td>
                    @else
                        <td colspan="9" class="{{ $loop->first ? '' : 'border-left' }}"></td>
                    @endif
                @endforeach
            </tr>
        @endfor
        @if ($lines === 0)
            <tr><td colspan="{{ 9 * count($byFuel) }}" class="text-muted">{{ __('confirm.nothing_here') }}</td></tr>
        @endif
        </tbody>
        @if ($lines > 0)
            <tfoot>
            <tr class="font-weight-bold">
                @foreach ($byFuel as $fuel => $rows)
                    <td colspan="4" class="{{ $loop->first ? '' : 'border-left' }}">{{ __('confirm.total') }}</td>
                    <td class="text-right">{{ number_format($rows->sum('preset_kg'), 2) }}</td>
                    <td class="text-right">{{ number_format($rows->sum('out_amount_kg'), 2) }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                @endforeach
            </tr>
            </tfoot>
        @endif
    </table>
</div>
