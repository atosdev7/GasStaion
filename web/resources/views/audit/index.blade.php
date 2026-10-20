@extends('layouts.app')

@section('title', __('menu.audit'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('audit.index') }}" class="form-inline">
                <label for="f_from" class="mr-2">{{ __('admin.from') }}</label>
                <input id="f_from" name="from" type="date" value="{{ $from }}" class="form-control mr-3">
                <label for="f_to" class="mr-2">{{ __('admin.to') }}</label>
                <input id="f_to" name="to" type="date" value="{{ $to }}" class="form-control mr-3">
                <label for="f_user" class="mr-2">{{ __('audit.user') }}</label>
                <input id="f_user" name="user" type="text" value="{{ $user }}" class="form-control mr-3">
                <label for="f_action" class="mr-2">{{ __('audit.action') }}</label>
                <input id="f_action" name="action" type="text" value="{{ $action }}" class="form-control mr-3">
                <button type="submit" class="btn btn-primary">{{ __('audit.show') }}</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('audit.entries', ['count' => $entries->total()]) }}</h3>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover">
                <thead>
                <tr>
                    <th class="text-nowrap">{{ __('audit.when') }}</th>
                    <th>{{ __('audit.user') }}</th>
                    <th>{{ __('audit.action') }}</th>
                    <th>{{ __('audit.detail') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="text-nowrap">{{ $entry->at }}</td>
                        <td>{{ $entry->who }}</td>
                        <td class="text-nowrap"><code>{{ $entry->action }}</code></td>
                        <td>{{ $entry->detail }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">{{ __('admin.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <div class="text-muted mb-2">{{ __('audit.hint') }}</div>
            {{ $entries->links() }}
        </div>
    </div>
@endsection
