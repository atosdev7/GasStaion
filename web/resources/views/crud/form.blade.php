@extends('layouts.app')

@section('title', __('menu.' . $title))

@section('content')
    <div class="card">
        <form method="POST" action="{{ $record->exists ? route($route . '.update', $record->getKey()) : route($route . '.store') }}">
            @csrf
            @if ($record->exists) @method('PUT') @endif
            <div class="card-body">
                @foreach ($fields as $name => $field)
                    <div class="form-group">
                        <label for="f_{{ $name }}">{{ $fieldLabel($name) }}</label>
                        @if ($field['type'] === 'select')
                            @php($locked = !empty($field['locked_on_edit']) && $record->exists)
                            @if ($locked)
                                <input type="hidden" name="{{ $name }}" value="{{ $record->{$name} }}">
                            @endif
                            <select id="f_{{ $name }}" @if (!$locked) name="{{ $name }}" @else disabled @endif class="form-control @error($name) is-invalid @enderror">
                                @foreach ($field['options'] as $value => $label)
                                    <option value="{{ $value }}" {{ (string) old($name, $record->{$name}) === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="f_{{ $name }}" name="{{ $name }}" type="{{ $field['type'] }}"
                                   @if (isset($field['step'])) step="{{ $field['step'] }}" @endif
                                   value="{{ $field['type'] === 'password' ? '' : old($name, $record->{$name}) }}"
                                   autocomplete="off"
                                   class="form-control @error($name) is-invalid @enderror">
                        @endif
                        @if (!empty($field['hint']))
                            <small class="form-text text-muted">{{ __($field['hint']) }}</small>
                        @endif
                        @error($name) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                @endforeach
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ __($langFile . '.save') }}</button>
                <a href="{{ route($route . '.index') }}" class="btn btn-default">{{ __($langFile . '.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection

{{-- a resource may add a script to its form: resources/views/crud/script_<route>.blade.php --}}
@includeIf('crud.script_' . $route)
