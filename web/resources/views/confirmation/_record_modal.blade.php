{{-- The popup that shows one record (its detail page without the layout) and the drop-down menus of the actions of the tables. --}}
<div class="modal fade" id="record-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('confirm.sale_details') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="record-modal-body"></div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        $(function () {
            // The menu of the actions is fixed to the window (the table scrolls sideways and would cut it) and sits under its button.
            $(document).on('shown.bs.dropdown', '.confirm-actions', function () {
                var button = $(this).find('.dropdown-toggle')[0].getBoundingClientRect();
                var menu = $(this).find('.dropdown-menu');
                menu.css({position: 'fixed', top: button.bottom + 'px', left: Math.max(4, button.right - menu.outerWidth()) + 'px', right: 'auto', transform: 'none'});
            });
            $(window).on('scroll resize', function () { $('.confirm-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });
            $('.table-responsive').on('scroll', function () { $('.confirm-actions .dropdown-toggle[aria-expanded=true]').dropdown('hide'); });

            $(document).on('click', 'a.record-detail', function (e) {
                e.preventDefault();
                var body = $('#record-modal-body');
                body.html('<div class="text-center text-muted p-4"><i class="fas fa-spinner fa-spin"></i></div>');
                $('#record-modal').modal('show');
                $.get($(this).attr('href'), {modal: 1})
                    .done(function (html) { body.html(html); })
                    .fail(function () { body.text(@json(__('confirm.no_data'))); });
            });
        });
    </script>
@endpush
