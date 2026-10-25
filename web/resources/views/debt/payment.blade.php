@extends('layouts.app')

@section('title', __('debts.payment_details'))

@section('content')
    @include('debt._payment')
    <a href="{{ route('payments.index', ['tab' => 'payments']) }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> {{ __('debts.back_to_list') }}</a>
@endsection
