@extends('layouts.app')

@section('title', __('menu.sync'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('sync.devices') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('sync.device') }}</th>
                    <th>{{ __('sync.last_synced') }}</th>
                    <th>{{ __('sync.state') }}</th>
                    <th>{{ __('sync.initial_done') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($devices as $device)
                    <tr>
                        <td>{{ $device->name }}</td>
                        <td>
                            @if ($device->last_synced_at)
                                {{ $device->last_synced_at->format('Y-m-d H:i:s') }} ({{ $device->last_synced_at->diffForHumans() }})
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if (!$device->last_synced_at)
                                <span class="badge badge-secondary">{{ __('sync.never') }}</span>
                            @elseif ($device->last_synced_at->lt($staleAfter))
                                <span class="badge badge-warning">{{ __('sync.stale') }}</span>
                            @else
                                <span class="badge badge-success">{{ __('sync.ok') }}</span>
                            @endif
                        </td>
                        <td>{{ $device->initial_sync_done ? __('sync.yes') : __('sync.no') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">-</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted">{{ __('sync.stale_hint', ['hours' => config('sync.stale_hours')]) }}</div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('sync.errors') }} <span class="badge badge-danger ml-2">{{ __('sync.open_count', ['count' => $openCount]) }}</span></h3>
            <div class="card-tools">
                @if ($all)
                    <a href="{{ route('sync.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('sync.show_open') }}</a>
                @else
                    <a href="{{ route('sync.index', ['all' => 1]) }}" class="btn btn-sm btn-outline-secondary">{{ __('sync.show_all') }}</a>
                @endif
            </div>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover">
                <thead>
                <tr>
                    <th>{{ __('sync.when') }}</th>
                    <th>{{ __('sync.device') }}</th>
                    <th>{{ __('sync.table') }}</th>
                    <th>{{ __('sync.record') }}</th>
                    <th>{{ __('sync.reason') }}</th>
                    <th class="text-right">{{ __('sync.times') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($errors as $error)
                    <tr class="{{ $error->resolved_at ? 'text-muted' : '' }}">
                        <td class="text-nowrap">{{ $error->updated_at }}</td>
                        <td class="text-nowrap">{{ optional($error->device)->name ?? $error->device_id }}</td>
                        <td>{{ $error->entity }}</td>
                        <td class="text-nowrap">{{ $error->record_id }}</td>
                        <td>
                            @if (strpos($error->reason, 'conflict:') === 0)
                                <span class="badge badge-warning">{{ __('sync.conflict') }}</span> {{ substr($error->reason, 9) }}
                            @else
                                {{ $error->reason }}
                            @endif
                            @if ($error->payload)
                                @php($pretty = json_encode(json_decode($error->payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                                <details class="mt-1"><summary class="small text-muted">{{ __('sync.payload') }}</summary><pre class="mb-0 small">{{ $pretty }}</pre></details>
                            @endif
                        </td>
                        <td class="text-right">{{ $error->occurrences }}</td>
                        <td class="text-nowrap">
                            @if ($error->resolved_at)
                                {{ __('sync.resolved_by', ['name' => $users[$error->resolved_by] ?? $error->resolved_by, 'date' => $error->resolved_at->format('Y-m-d H:i')]) }}
                            @else
                                @can('sync.manage')
                                    <form method="POST" action="{{ route('sync.resolve', ['id' => $error->id, 'all' => $all ? 1 : null]) }}" onsubmit="return confirm(@json(__('sync.resolve_confirm')))">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-check"></i> {{ __('sync.resolve') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">{{ __('sync.no_errors') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($errors->hasPages())
            <div class="card-footer">{{ $errors->links() }}</div>
        @endif
    </div>
@endsection
