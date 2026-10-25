@extends('layouts.app')

@section('title', __('tickets.publish'))

@section('content')
    @if ($stock->isEmpty())
        <div class="alert alert-warning">{{ __('tickets.no_blank_stock') }}</div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('ticket-publish.store') }}" id="publish-form">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label for="f_stock_item">{{ __('tickets.blank_stock') }}</label>
                    <select id="f_stock_item" name="stock_item" class="form-control @error('stock_item') is-invalid @enderror">
                        @foreach ($stock as $row)
                            @php($value = $row->fuel_type . ':' . $row->spec)
                            <option value="{{ $value }}" data-next="{{ $nextSerials[$value] ?? 1 }}" {{ old('stock_item') === $value ? 'selected' : '' }}>
                                {{ $fuels[$row->fuel_type] ?? $row->fuel_type }} - {{ $specs[$row->spec] ?? $row->spec }} {{ __('tickets.kg') }} ({{ __('tickets.available') }}: {{ $row->available }})
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('tickets.stock_item_hint') }}</small>
                    @error('stock_item') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_date">{{ __('tickets.date') }}</label>
                    <input id="f_date" name="date" type="date" value="{{ old('date', date('Y-m-d')) }}" class="form-control @error('date') is-invalid @enderror">
                    @error('date') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_start_serial">{{ __('tickets.start_serial') }}</label>
                    <input id="f_start_serial" name="start_serial" type="number" step="1" min="1" value="{{ old('start_serial') }}" class="form-control @error('start_serial') is-invalid @enderror">
                    @error('start_serial') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="f_count">{{ __('tickets.quantity') }}</label>
                    <input id="f_count" type="number" step="1" min="1" class="form-control">
                </div>
                <div class="form-group">
                    <label for="f_end_serial">{{ __('tickets.end_serial') }}</label>
                    <input id="f_end_serial" name="end_serial" type="number" step="1" min="1" value="{{ old('end_serial') }}" class="form-control @error('end_serial') is-invalid @enderror">
                    @error('end_serial') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <p class="text-muted">{{ __('tickets.serial_range_hint') }}</p>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success">{{ __('tickets.publish') }}</button>
                <a href="{{ route('ticket-publish.index') }}" class="btn btn-default">{{ __('tickets.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var stockItem = document.getElementById('f_stock_item');
    var start = document.getElementById('f_start_serial');
    var count = document.getElementById('f_count');
    var end = document.getElementById('f_end_serial');

    function intVal(el) {
        var n = parseInt(el.value, 10);
        return isNaN(n) || n < 1 ? null : n;
    }

    function fillStartFromStock() {
        var opt = stockItem.options[stockItem.selectedIndex];
        if (opt && !start.value) {
            start.value = opt.getAttribute('data-next');
        }
    }

    function onStockChange() {
        var opt = stockItem.options[stockItem.selectedIndex];
        if (!opt) return;
        start.value = opt.getAttribute('data-next');
        recalcEndFromCount();
    }

    function recalcEndFromCount() {
        var s = intVal(start), c = intVal(count);
        if (s !== null && c !== null) {
            end.value = s + c - 1;
        }
    }

    function recalcCountFromEnd() {
        var s = intVal(start), e = intVal(end);
        if (s !== null && e !== null && e >= s) {
            count.value = e - s + 1;
        }
    }

    // Publishing cannot be reversed: the user is asked first, and told so in the question.
    var question = @json(__('tickets.publish_confirm'));
    document.getElementById('publish-form').addEventListener('submit', function (event) {
        var opt = stockItem.options[stockItem.selectedIndex];
        var s = intVal(start), e = intVal(end);
        var text = question
            .replace(':count', s !== null && e !== null && e >= s ? (e - s + 1) : '?')
            .replace(':item', opt ? opt.text.replace(/\s+/g, ' ').trim() : '')
            .replace(':start', s !== null ? s : '?')
            .replace(':end', e !== null ? e : '?');
        if (!window.confirm(text)) {
            event.preventDefault();
        }
    });

    stockItem.addEventListener('change', onStockChange);
    count.addEventListener('input', recalcEndFromCount);
    end.addEventListener('input', recalcCountFromEnd);
    start.addEventListener('input', function () {
        if (intVal(count) !== null) {
            recalcEndFromCount();
        } else {
            recalcCountFromEnd();
        }
    });

    // Pre-fill on first load (old('start_serial') from a failed submit takes priority).
    fillStartFromStock();
    recalcCountFromEnd();
})();
</script>
@endpush
