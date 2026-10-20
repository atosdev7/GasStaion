@extends('layouts.app')

@section('title', __('menu.supplements'))

@section('actions')
    <a href="{{ route('supplements.index') }}" class="btn btn-default"><i class="fas fa-list"></i> {{ __('supplements.back_to_list') }}</a>
@endsection

@section('content')
    @include('supplement._detail')
@endsection
