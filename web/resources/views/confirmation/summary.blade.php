@extends('layouts.app')

@section('title', __('menu.group_confirmations') . ' - ' . __('menu.confirmation_summary'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @include('confirmation._header', ['route' => 'confirmations.summary'])

    <div class="card">
        <div class="card-body">
            <div class="clearfix mb-2">
                <a href="{{ route('confirmations.summary-export', ['date' => $date]) }}" class="btn btn-outline-success btn-sm float-right"><i class="fas fa-file-excel"></i> {{ __('confirm.export_xls') }}</a>
            </div>
            @include('confirmation._summary')
        </div>
    </div>
@endsection
