@extends('layouts.app')

@section('title', __('menu.' . ($sale->sale_type === 'ticket' ? 'ticket' : $sale->sale_type) . '_sales'))

@section('actions')
    <a href="{{ route(App\Http\Controllers\Sales\SaleController::listRoute($sale->sale_type)) }}" class="btn btn-default"><i class="fas fa-list"></i> {{ __('sales.back_to_list') }}</a>
@endsection

@section('content')
    @include('sale._detail')
@endsection
