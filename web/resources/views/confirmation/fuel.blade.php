@extends('layouts.app')

@section('title', __('menu.group_confirmations') . ' - ' . __('menu.confirmation_fuel'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @include('confirmation._header', ['route' => 'confirmations.fuel', 'area' => 'fuel'])

    {{-- The fuel stock of the date: the fuel inbounds and the fuel outbounds made by hand (the outbound of a supply follows the supply). The confirm button confirms the
         ones checked that are not confirmed yet; Unconfirm (a manager) sends the same form to its own address (formaction). --}}
    <form method="POST" action="{{ route('confirmations.store') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="from" value="fuel">
        <p class="text-muted">{{ __('confirm.fuel_hint') }}</p>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('confirm.fuel_inbounds') }} ({{ $inbounds->count() }})</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th style="width: 30px"><input type="checkbox" class="check-all" data-target="check-fuel-inbounds" title="{{ __('confirm.check_all') }}"></th>
                        <th>#</th>
                        <th>{{ __('confirm.tank') }}</th>
                        <th>{{ __('confirm.fuel_type') }}</th>
                        <th>{{ __('confirm.from_name') }}</th>
                        <th class="text-right">{{ __('confirm.amount_kg') }}</th>
                        <th class="text-right">{{ __('confirm.cost') }}</th>
                        <th>{{ __('confirm.note') }}</th>
                        <th class="text-right">{{ __('confirm.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($inbounds as $row)
                        <tr>
                            <td>
                                @if ($row->isConfirmed())
                                    <i class="fas fa-check-circle text-success" title="{{ __('confirm.confirmed') }}"></i><span class="sr-only">{{ __('confirm.confirmed') }}</span>
                                @else
                                    <input type="checkbox" name="fuel_inbounds[]" value="{{ $row->id }}" class="check-fuel-inbounds">
                                @endif
                            </td>
                            <td>{{ $row->id }}</td>
                            <td>{{ $tanks[$row->tank_id] ?? $row->tank_id }}</td>
                            <td>{{ $fuels[$row->fuel_type] ?? $row->fuel_type }}</td>
                            <td>{{ $row->from_name }}</td>
                            <td class="text-right">{{ number_format($row->amount_kg, 2) }}</td>
                            <td class="text-right">{{ $row->cost !== null ? number_format($row->cost, 4) : '' }}</td>
                            <td>{{ $row->note }}</td>
                            <td class="text-right">
                                @if ($row->isConfirmed())
                                    @can('confirmations.unconfirm')
                                        <button type="submit" formmethod="post" formaction="{{ route('inbound.unconfirm', $row->id) }}" class="btn btn-xs btn-outline-secondary"
                                                onclick="return confirm(@json(__('confirm.unconfirm_confirm')))"><i class="fas fa-undo fa-fw"></i> {{ __('confirm.unconfirm') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-muted">{{ __('confirm.nothing_here') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if ($inbounds->isNotEmpty())
                        <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="5">{{ __('confirm.total') }}</td>
                            <td class="text-right">{{ number_format($inbounds->sum('amount_kg'), 2) }}</td>
                            <td colspan="3"></td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('confirm.fuel_outbounds') }} ({{ $outbounds->count() }})</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th style="width: 30px"><input type="checkbox" class="check-all" data-target="check-fuel-outbounds" title="{{ __('confirm.check_all') }}"></th>
                        <th>#</th>
                        <th>{{ __('confirm.tank') }}</th>
                        <th>{{ __('confirm.to_name') }}</th>
                        <th class="text-right">{{ __('confirm.amount_kg') }}</th>
                        <th>{{ __('confirm.note') }}</th>
                        <th class="text-right">{{ __('confirm.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($outbounds as $row)
                        <tr>
                            <td>
                                @if ($row->isConfirmed())
                                    <i class="fas fa-check-circle text-success" title="{{ __('confirm.confirmed') }}"></i><span class="sr-only">{{ __('confirm.confirmed') }}</span>
                                @else
                                    <input type="checkbox" name="fuel_outbounds[]" value="{{ $row->id }}" class="check-fuel-outbounds">
                                @endif
                            </td>
                            <td>{{ $row->id }}</td>
                            <td>{{ $tanks[$row->tank_id] ?? $row->tank_id }}</td>
                            <td>{{ $row->to_name }}</td>
                            <td class="text-right">{{ number_format($row->amount_kg, 2) }}</td>
                            <td>{{ $row->note }}</td>
                            <td class="text-right">
                                @if ($row->isConfirmed())
                                    @can('confirmations.unconfirm')
                                        <button type="submit" formmethod="post" formaction="{{ route('outbound.unconfirm', $row->id) }}" class="btn btn-xs btn-outline-secondary"
                                                onclick="return confirm(@json(__('confirm.unconfirm_confirm')))"><i class="fas fa-undo fa-fw"></i> {{ __('confirm.unconfirm') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">{{ __('confirm.nothing_here') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if ($outbounds->isNotEmpty())
                        <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="4">{{ __('confirm.total') }}</td>
                            <td class="text-right">{{ number_format($outbounds->sum('amount_kg'), 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        @if ($inbounds->contains(function ($row) { return !$row->isConfirmed(); }) || $outbounds->contains(function ($row) { return !$row->isConfirmed(); }))
            <button type="submit" class="btn btn-success" onclick="return confirm(@json(__('confirm.confirm_question', ['date' => $date])))">
                <i class="fas fa-check-double"></i> {{ __('confirm.confirm') }}
            </button>
            <small class="text-muted ml-2">{{ __('confirm.confirm_hint') }}</small>
        @endif
    </form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.check-all').forEach(function (box) {
    box.addEventListener('change', function () {
        document.querySelectorAll('.' + box.getAttribute('data-target')).forEach(function (el) { el.checked = box.checked; });
    });
});
</script>
@endpush
