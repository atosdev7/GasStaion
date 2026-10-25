@extends('layouts.app')

@section('title', __('debts.debt_details'))

@section('content')
    @include('debt._detail')
    <a href="{{ route('payments.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> {{ __('debts.back_to_list') }}</a>
@endsection