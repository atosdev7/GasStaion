<div class="mb-2"><a href="{{ route('reports.index') }}"><i class="fas fa-arrow-left"></i> {{ __('report.all_reports') }}</a></div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ url()->current() }}" class="form-inline">
            @isset($customers)
                <label for="f_customer_id" class="mr-2">{{ __('report.customer_id') }}</label>
                <select id="f_customer_id" name="customer_id" class="form-control js-select2 mr-3" data-width="280px">
                    <option value="">{{ __('report.choose_customer') }}</option>
                    @foreach ($customers as $id => $name)
                        <option value="{{ $id }}" {{ (string) $customerId === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            @endisset
            <label for="f_from" class="mr-2">{{ __('report.from') }}</label>
            <input id="f_from" name="from" type="date" value="{{ $from }}" class="form-control mr-3">
            <label for="f_to" class="mr-2">{{ __('report.to') }}</label>
            <input id="f_to" name="to" type="date" value="{{ $to }}" class="form-control mr-3">
            <button type="submit" class="btn btn-primary">{{ __('report.show') }}</button>
            <button type="submit" name="export" value="xls" class="btn btn-outline-success ml-2"><i class="fas fa-file-excel"></i> {{ __('report.export_xls') }}</button>
        </form>
        @if ($errors->any())
            <div class="text-danger mt-2">{{ $errors->first() }}</div>
        @endif
    </div>
</div>
