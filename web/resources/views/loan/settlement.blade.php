@extends('layouts.app')

@section('title', __('debts.settlement_details'))

@section('content')
    @include('loan._settlement')
    <a href="{{ route('loans.index', ['tab' => 'settlements']) }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> {{ __('debts.back_to_list') }}</a>
@endsection
