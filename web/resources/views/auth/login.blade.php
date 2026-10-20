<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.login') }} - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center"><span class="h1">{{ config('app.name') }}</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="input-group mb-3">
                    <input name="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" placeholder="{{ __('app.username') }}" autofocus>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
                </div>
                <div class="input-group mb-3">
                    <input name="password" type="password" class="form-control" placeholder="{{ __('app.password') }}">
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>
                @error('username') <div class="text-danger mb-3">{{ $message }}</div> @enderror
                <button type="submit" class="btn btn-primary btn-block">{{ __('app.login') }}</button>
            </form>
            @if (count(config('locales')) > 1)
            <p class="mt-3 mb-0 text-center">
                @foreach (config('locales') as $code => $name)
                    <a href="{{ route('locale', $code) }}">{{ $name }}</a>@if (!$loop->last) | @endif
                @endforeach
            </p>
            @endif
        </div>
    </div>
</div>
</body>
</html>
