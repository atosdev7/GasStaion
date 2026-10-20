@extends('layouts.app')

@section('title', __('menu.' . $title))

@section('actions')
    @can($perm . '.manage')
        @if (!$trashed)
            <a href="{{ route($route . '.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __($langFile . '.new') }}</a>
        @endif
        <a href="{{ route($route . '.index', $trashed ? [] : ['trashed' => 1]) }}" class="btn btn-outline-secondary">
            @if ($trashed)
                <i class="fas fa-list"></i> {{ __($langFile . '.show_active') }}
            @else
                <i class="fas fa-trash"></i> {{ __($langFile . '.show_trash') }}
            @endif
        </a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                <tr>
                    <th>#</th>
                    @foreach ($fields as $name => $field)
                        @if ($field['type'] !== 'password' && ($field['list'] ?? true))
                            <th>{{ $fieldLabel($name) }}</th>
                        @endif
                    @endforeach
                    @if ($hasActive && !$trashed)
                        <th>{{ __($langFile . '.status') }}</th>
                    @endif
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row->getKey() }}</td>
                        @foreach ($fields as $name => $field)
                            @if ($field['type'] !== 'password' && ($field['list'] ?? true))
                                <td>{{ isset($field['options']) ? ($field['options'][$row->{$name}] ?? $row->{$name}) : $row->{$name} }}</td>
                            @endif
                        @endforeach
                        @if ($hasActive && !$trashed)
                            <td>
                                <span class="badge {{ $row->active ? 'badge-success' : 'badge-secondary' }}">{{ $row->active ? __($langFile . '.active') : __($langFile . '.inactive') }}</span>
                            </td>
                        @endif
                        <td class="text-right">
                            @can($perm . '.manage')
                                @if ($trashed)
                                    <form method="POST" action="{{ route($route . '.restore', $row->getKey()) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">{{ __($langFile . '.restore') }}</button>
                                    </form>
                                @else
                                    <a href="{{ route($route . '.edit', $row->getKey()) }}" class="btn btn-sm btn-outline-primary">{{ __($langFile . '.edit') }}</a>
                                    @if ($modifiable($row))
                                        @if ($hasActive)
                                            <form method="POST" action="{{ route($route . '.toggle', $row->getKey()) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $row->active ? __($langFile . '.deactivate') : __($langFile . '.activate') }}</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route($route . '.destroy', $row->getKey()) }}" class="d-inline" onsubmit="return confirm('{{ __($langFile . '.delete_confirm') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __($langFile . '.delete') }}</button>
                                        </form>
                                    @endif
                                @endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
