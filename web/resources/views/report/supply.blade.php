@extends('layouts.app')

@section('title', __('report.title_supply'))

@section('content')
    @include('report._period')

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('report.day') }}</th>
                    <th>{{ __('report.nozzle_id') }}</th>
                    <th class="text-right">{{ __('report.count') }}</th>
                    <th class="text-right">{{ __('report.liters') }}</th>
                    <th class="text-right">{{ __('report.amount_kg') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $r->day }}</td>
                        <td>{{ $nozzleLabels[$r->nozzle_id] ?? $r->nozzle_id }}</td>
                        <td class="text-right">{{ $r->supplies }}</td>
                        <td class="text-right">{{ number_format($r->liters, 2) }}</td>
                        <td class="text-right">{{ number_format($r->kg, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">{{ __('report.no_data') }}</td></tr>
                @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="2">{{ __('report.total') }}</td>
                        <td class="text-right">{{ $rows->sum('supplies') }}</td>
                        <td class="text-right">{{ number_format($rows->sum('liters'), 2) }}</td>
                        <td class="text-right">{{ number_format($rows->sum('kg'), 2) }}</td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
