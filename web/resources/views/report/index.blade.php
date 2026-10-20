@extends('layouts.app')

@section('title', __('menu.reports'))

@section('content')
    <div class="row">
        @foreach ($reports as $report)
            <div class="col-md-4">
                <a href="{{ route('reports.' . $report) }}" class="card card-outline card-primary d-block text-reset">
                    <div class="card-body">
                        <h5 class="mb-1">{{ __('report.title_' . $report) }}</h5>
                        <p class="text-muted mb-0">{{ __('report.desc_' . $report) }}</p>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection
