@extends('layouts.app')

@section('title', __('menu.backup'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('backup.index') }}" class="form-inline">
                <label for="f_up_to" class="mr-2">{{ __('admin.backup_up_to') }}</label>
                <input id="f_up_to" name="up_to" type="date" value="{{ $upTo }}" max="{{ today()->subDay()->toDateString() }}" class="form-control mr-2">
                <button type="submit" class="btn btn-primary">{{ __('admin.backup_preview') }}</button>
            </form>
            <small class="form-text text-muted">{{ __('admin.backup_kept_hint') }}</small>
            <small class="form-text text-muted">{{ __('admin.backup_daily_hint', ['days' => config('backup.keep_days')]) }}</small>
        </div>
    </div>

    @if ($preview)
        <div class="card card-outline card-danger">
            <div class="card-header"><h3 class="card-title">{{ __('admin.backup_will_clear', ['date' => $upTo]) }}</h3></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            @foreach ($preview as $table => $count)
                                <tr><td>{{ __('admin.backup_table_' . $table) }}</td><td class="text-right">{{ $count }}</td></tr>
                            @endforeach
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>{{ __('admin.backup_tank_carry') }}</h6>
                        <table class="table table-sm">
                            @forelse ($carry as $tankId => $kg)
                                <tr><td>{{ $tanks[$tankId] ?? $tankId }}</td><td class="text-right">{{ number_format($kg, 2) }} {{ __('admin.kg') }}</td></tr>
                            @empty
                                <tr><td class="text-muted">-</td></tr>
                            @endforelse
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('backup.store') }}" onsubmit="return confirm(@json(__('admin.backup_confirm')))">
                    @csrf
                    <input type="hidden" name="up_to" value="{{ $upTo }}">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-file-archive"></i> {{ __('admin.backup_run') }}</button>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ __('admin.backup_history') }}</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>{{ __('admin.backup_date') }}</th>
                    <th>{{ __('admin.backup_up_to') }}</th>
                    <th>{{ __('admin.backup_cleared') }}</th>
                    <th>{{ __('admin.backup_file') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($backups as $b)
                    <tr>
                        <td>{{ $b->created_at }}</td>
                        <td>{{ $b->up_to_date }}</td>
                        <td>
                            @foreach ($b->cleared as $table => $count)
                                {{ __('admin.backup_table_' . $table) }}: {{ $count }}@if (!$loop->last), @endif
                            @endforeach
                        </td>
                        <td><a href="{{ route('backup.download', $b->id) }}"><i class="fas fa-download"></i> {{ $b->file }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
