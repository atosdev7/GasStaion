<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('app.name')) - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li>
        </ul>
        <ul class="navbar-nav ml-auto">
            @if (count(config('locales')) > 1)
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#"><i class="fas fa-language"></i> {{ config('locales.' . app()->getLocale()) }}</a>
                <div class="dropdown-menu dropdown-menu-right">
                    @foreach (config('locales') as $code => $name)
                        <a href="{{ route('locale', $code) }}" class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}">{{ $name }}</a>
                    @endforeach
                </div>
            </li>
            @endif
            <li class="nav-item">
                <span class="nav-link">{{ auth()->user()->name }} ({{ __('roles.' . auth()->user()->role) }})</span>
            </li>
            <li class="nav-item">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-link nav-link"><i class="fas fa-sign-out-alt"></i> {{ __('app.logout') }}</button>
                </form>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('home') }}" class="brand-link"><span class="brand-text font-weight-light pl-3">{{ config('app.name') }}</span></a>
        <div class="sidebar">
            @include('partials.sidebar')
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
                <h1 class="mr-3">@yield('title', __('app.name'))</h1>
                @hasSection('actions')
                    <div class="page-actions">@yield('actions')</div>
                @endif
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>

    <footer class="main-footer"><strong>{{ config('app.name') }}</strong></footer>
</div>
<script src="{{ asset('vendor/adminlte/plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/adminlte/js/adminlte.min.js') }}"></script>
<script src="{{ asset('vendor/adminlte/plugins/select2/js/select2.min.js') }}"></script>
<script src="{{ asset('vendor/adminlte/plugins/moment/moment.min.js') }}"></script>
<script src="{{ asset('vendor/adminlte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
<script src="{{ asset('js/datepicker.js') }}"></script>
<script src="{{ asset('js/select2-init.js') }}"></script>
@stack('scripts')
</body>
</html>
