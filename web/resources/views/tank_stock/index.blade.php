@extends('layouts.app')

@section('title', __('menu.tank_stock'))

@section('content')
    <p class="text-muted">{{ __('fuel.stock_hint', ['time' => $asOf]) }}</p>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('fuel.stock_by_fuel') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap" id="stock-by-fuel">
                <thead>
                <tr>
                    <th>{{ __('fuel.fuel_type') }}</th>
                    <th class="text-right">{{ __('fuel.stock_tanks') }}</th>
                    <th class="text-right">{{ __('fuel.stock_balance') }}</th>
                    <th class="text-right">{{ __('fuel.stock_capacity') }}</th>
                    <th class="text-right">{{ __('fuel.stock_value') }}</th>
                    <th class="text-right">{{ __('fuel.stock_average') }}</th>
                    <th class="text-right">{{ __('fuel.stock_current_cost') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($fuels as $fuel)
                    <tr>
                        <td>{{ $names[$fuel['fuel_type']] ?? $fuel['fuel_type'] }}</td>
                        <td class="text-right">{{ $fuel['tanks'] }}</td>
                        <td class="text-right">{{ number_format($fuel['balance'], 2) }}</td>
                        <td class="text-right">{{ $fuel['capacity'] !== null ? number_format($fuel['capacity'], 2) : '-' }}</td>
                        <td class="text-right">{{ number_format($fuel['value'], 2) }}</td>
                        <td class="text-right">{{ $fuel['average'] !== null ? number_format($fuel['average'], 4) : '-' }}</td>
                        <td class="text-right">{{ $fuel['default_cost'] !== null ? number_format($fuel['default_cost'], 4) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">{{ __('fuel.no_data') }}</td></tr>
                @endforelse
                </tbody>
                @if (count($fuels) > 0)
                    <tfoot>
                    <tr class="font-weight-bold">
                        <td>{{ __('fuel.stock_total') }}</td>
                        <td></td>
                        <td class="text-right">{{ number_format(array_sum(array_column($fuels, 'balance')), 2) }}</td>
                        <td></td>
                        <td class="text-right" id="stock-total-value">{{ number_format($value, 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @foreach ($fuels as $fuel)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('fuel.stock_by_cost', ['fuel' => $names[$fuel['fuel_type']] ?? $fuel['fuel_type']]) }}</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap" id="stock-layers-{{ $fuel['fuel_type'] }}">
                    <thead>
                    <tr>
                        <th>{{ __('fuel.inbound_date') }}</th>
                        <th class="text-right">{{ __('fuel.cost') }}</th>
                        <th class="text-right">{{ __('fuel.stock_left') }}</th>
                        <th class="text-right">{{ __('fuel.stock_received') }}</th>
                        <th class="text-right">{{ __('fuel.stock_value') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($fuel['layers'] as $layer)
                        <tr>
                            <td>{{ $layer['inbound_date'] }} @if ($layer['is_default'])<span class="badge badge-success">{{ __('fuel.default') }}</span>@endif</td>
                            <td class="text-right">{{ number_format($layer['cost'], 4) }}</td>
                            <td class="text-right">{{ number_format($layer['kg'], 2) }}</td>
                            <td class="text-right text-muted">{{ number_format($layer['of'], 2) }}</td>
                            <td class="text-right">{{ number_format($layer['value'], 2) }}</td>
                        </tr>
                    @endforeach
                    @if ($fuel['unknown_kg'] > 0.004)
                        <tr class="text-muted">
                            <td>{{ __('fuel.stock_unknown') }}</td>
                            <td class="text-right">-</td>
                            <td class="text-right">{{ number_format($fuel['unknown_kg'], 2) }}</td>
                            <td></td>
                            <td class="text-right">-</td>
                        </tr>
                    @endif
                    @if (count($fuel['layers']) == 0 && $fuel['unknown_kg'] <= 0.004)
                        <tr><td colspan="5" class="text-muted">{{ __('fuel.stock_empty') }}</td></tr>
                    @endif
                    </tbody>
                    <tfoot>
                    <tr class="font-weight-bold">
                        <td>{{ __('fuel.stock_total') }}</td>
                        <td class="text-right">{{ $fuel['average'] !== null ? number_format($fuel['average'], 4) : '-' }}</td>
                        <td class="text-right">{{ number_format($fuel['balance'], 2) }}</td>
                        <td></td>
                        <td class="text-right">{{ number_format($fuel['value'], 2) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endforeach

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('fuel.stock_by_tank') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap" id="stock-by-tank">
                <thead>
                <tr>
                    <th>{{ __('fuel.tank_id') }}</th>
                    <th>{{ __('fuel.fuel_type') }}</th>
                    <th class="text-right">{{ __('fuel.stock_opening') }}</th>
                    <th class="text-right">{{ __('fuel.stock_in') }}</th>
                    <th class="text-right">{{ __('fuel.stock_out') }}</th>
                    <th class="text-right">{{ __('fuel.stock_balance') }}</th>
                    <th class="text-right">{{ __('fuel.stock_capacity') }}</th>
                    <th class="text-right">%</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($tanks as $row)
                    <tr>
                        <td>{{ $row->tank->name }}</td>
                        <td>{{ $names[$row->tank->fuel_type] ?? $row->tank->fuel_type }}</td>
                        <td class="text-right">{{ number_format($row->opening, 2) }}</td>
                        <td class="text-right">{{ number_format($row->inbound, 2) }}</td>
                        <td class="text-right">{{ number_format($row->supplied, 2) }}</td>
                        <td class="text-right">{{ number_format($row->current, 2) }}</td>
                        <td class="text-right">{{ $row->capacity !== null ? number_format($row->capacity, 2) : '-' }}</td>
                        <td class="text-right">{{ $row->percent !== null ? $row->percent : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('fuel.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
