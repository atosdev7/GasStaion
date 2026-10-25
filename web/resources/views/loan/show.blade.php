@extends('layouts.app')

@section('title', __('debts.loan_details'))

@section('content')
    @include('loan._detail')
    <a href="{{ route('loans.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> {{ __('debts.back_to_list') }}</a>
@endsection
