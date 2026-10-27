@extends('layouts.app')

@section('title', __('menu.group_confirmations') . ' - ' . __('menu.confirmation_overview'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('confirmation._header', ['route' => 'confirmations.overview'])

    {{-- The areas of the daily confirmation for the date, in the order of the menu: how many records, how many confirmed, how many wait --}}
    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap mb-0" id="overview-table">
                <thead>
                <tr>
                    <th>{{ __('confirm.overview_area') }}</th>
                    <th class="text-right">{{ __('confirm.overview_total') }}</th>
                    <th class="text-right">{{ __('confirm.overview_confirmed') }}</th>
                    <th class="text-right">{{ __('confirm.overview_waiting') }}</th>
                    <th></th>
                    <th class="text-right"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($areas as $key => $area)
                    <tr class="overview-{{ $key }}">
                        <td>{{ __('menu.confirmation_' . ($key === 'sales' ? 'sales' : $key)) }}</td>
                        <td class="text-right">{{ $area[1]['total'] }}</td>
                        <td class="text-right">{{ $area[1]['confirmed'] }}</td>
                        <td class="text-right {{ $area[1]['waiting'] ? 'font-weight-bold text-warning' : '' }}">{{ $area[1]['waiting'] }}</td>
                        <td>
                            @if ($area[1]['total'] === 0)
                                <span class="text-muted">-</span>
                            @elseif ($area[1]['waiting'] === 0)
                                <i class="fas fa-check-circle text-success" title="{{ __('confirm.confirmed') }}"></i>
                            @else
                                <i class="fas fa-exclamation-circle text-warning" title="{{ __('confirm.overview_waiting') }}"></i>
                            @endif
                        </td>
                        <td class="text-right"><a href="{{ route($area[0], ['date' => $date]) }}" class="btn btn-xs btn-outline-primary">{{ __('confirm.overview_open') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- what to look at before confirming --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('confirm.overview_look') }}</h3></div>
        <div class="card-body">
            @php($any = false)
            @if ($warnings['silent'])
                @php($any = true)
                <div class="alert alert-warning overview-silent">{{ __('confirm.overview_silent', ['time' => $warnings['dayEnds']->format('Y-m-d H:i'), 'devices' => implode(', ', $warnings['silent'])]) }}</div>
            @endif
            @if ($warnings['noCost'])
                @php($any = true)
                <div class="alert alert-warning overview-no-cost">{{ __('confirm.overview_no_cost', ['count' => $warnings['noCost']]) }}</div>
            @endif
            @if ($warnings['noTickets'])
                @php($any = true)
                <div class="alert alert-warning overview-no-tickets">{{ __('confirm.overview_no_tickets', ['count' => $warnings['noTickets']]) }}</div>
            @endif
            @if ($warnings['supplyChecks'])
                @php($any = true)
                <div class="alert alert-warning overview-supply-checks">
                    {{ __('confirm.overview_supply_checks') }}
                    <ul class="mb-0">
                        @foreach ($warnings['supplyChecks'] as $check)
                            <li>
                                @can('supplements.view')
                                    <a href="{{ route('supplements.show', $check['id']) }}">{{ $check['text'] }}</a>
                                @else
                                    {{ $check['text'] }}
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($warnings['unusedTickets'])
                @php($any = true)
                <div class="alert alert-info overview-unused-tickets">
                    {{ __('confirm.overview_unused_tickets') }}
                    @foreach ($warnings['unusedTickets'] as $type => $count)
                        @can('tickets.lookup')
                            <a href="{{ route('tickets.lookup', ['tab' => 'pos', 'type' => $type, 'status' => config('ticket_statuses.sale'), 'sold' => $date]) }}">{{ __('tickets.pos_type_' . $type) }}: {{ $count }}</a>@unless ($loop->last), @endunless
                        @else
                            {{ __('tickets.pos_type_' . $type) }}: {{ $count }}@unless ($loop->last), @endunless
                        @endcan
                    @endforeach
                </div>
            @endif
            @if ($warnings['deleted'])
                @php($any = true)
                <div class="alert alert-info overview-deleted">{{ __('confirm.overview_deleted', ['count' => $warnings['deleted']]) }}</div>
            @endif
            @unless ($any)
                <span class="text-muted">{{ __('confirm.overview_nothing') }}</span>
            @endunless
        </div>
    </div>
@endsection
