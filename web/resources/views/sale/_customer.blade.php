{{-- The customer of a sale: AdminLTE's Select2 (type to find a customer); a name that is not there yet can be added right here (customers.manage). --}}
@php
    $selectedNew = old('new_customer');
    $selectedId = old('customer_id', $selectedNew ? '' : (isset($defaultCustomer) ? $defaultCustomer : $customers->keys()->first()));
    $canAdd = auth()->user()->can('customers.manage');
@endphp
<label for="f_customer_name">{{ __('sales.customer_id') }}</label>
<select id="f_customer_name" class="form-control" style="width: 100%" data-can-add="{{ $canAdd ? '1' : '0' }}">
    <option value=""></option>
    @foreach ($customers as $id => $name)
        <option value="{{ $id }}" {{ (string) $selectedId === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
    @endforeach
    @if ($selectedNew && $canAdd)
        <option value="new:{{ $selectedNew }}" selected>{{ $selectedNew }}</option>
    @endif
</select>
{{-- what is posted: the id of the customer chosen, or the name typed for a new one --}}
<input type="hidden" name="customer_id" id="f_customer_id" value="{{ $selectedId }}">
<input type="hidden" name="new_customer" id="f_new_customer" value="{{ $selectedNew }}">
<small id="f_customer_new_note" class="text-success" style="display: none">{{ __('sales.customer_new_note') }}</small>
@error('customer_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
@error('new_customer') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
@push('scripts')
<script>
$(function () {
    var select = $('#f_customer_name');
    var canAdd = select.data('can-add') === 1;
    var addText = @json(__('sales.customer_add_new', ['name' => ':name']));

    select.select2({
        width: '100%', placeholder: @json(__('sales.customer_search')), language: @json(app()->getLocale()), tags: canAdd,
        createTag: function (params) {
            var text = $.trim(params.term);
            var same = false;
            select.find('option').each(function () { same = same || $(this).text().toLowerCase() === text.toLowerCase(); });

            return text === '' || same ? null : {id: 'new:' + text, text: text, newTag: true};
        },
        templateResult: function (item) {
            return item.newTag ? $('<span class="text-success font-weight-bold"></span>').text('+ ' + addText.replace(':name', item.text)) : item.text;
        }
    });

    function sync() {
        var value = select.val() || '';
        var isNew = value.indexOf('new:') === 0;
        $('#f_customer_id').val(isNew ? '' : value);
        $('#f_new_customer').val(isNew ? value.slice(4) : '');
        $('#f_customer_new_note').toggle(isNew);
    }

    select.on('change', sync);
    sync();
});
</script>
@endpush
