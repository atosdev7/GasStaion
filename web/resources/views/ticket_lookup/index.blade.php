@extends('layouts.app')

@section('title', __('menu.ticket_lookup'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @error('barcode')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    {{-- two tabs: the tickets printed by the press (published stock) and the tickets issued by a POS (the Smart210: spot sales, own use, loans, remainders, cards) --}}
    <ul class="nav nav-tabs mb-3" id="ticket-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab === 'press' ? 'active' : '' }}" href="{{ route('tickets.lookup', ['tab' => 'press']) }}">{{ __('tickets.tab_press') }}</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'pos' ? 'active' : '' }}" href="{{ route('tickets.lookup', ['tab' => 'pos']) }}">{{ __('tickets.tab_pos') }}</a></li>
    </ul>

    @include($tab === 'pos' ? 'ticket_lookup._pos' : 'ticket_lookup._press')
    <div class="modal fade" id="ticket-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('menu.ticket_lookup') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="ticket-modal-body"></div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    $(function () { // jQuery is loaded by the layout before this script
        var url = @json(route('tickets.lookup'));
        var openBarcode = @json($openBarcode);

        function openTicket(barcode) {
            var body = $('#ticket-modal-body');
            body.text('...');
            $('#ticket-modal').modal('show');
            $.get(url, {barcode: barcode, modal: 1, back: window.location.search.replace(/^\?/, '').replace(/(^|&)(barcode|page)=[^&]*/g, '').replace(/^&/, '')})
                .done(function (html) { body.html(html); })
                .fail(function () { body.text(@json(__('tickets.ticket_not_found'))); });
        }

        $(document).on('click', 'a.ticket-detail', function (e) {
            e.preventDefault();
            openTicket($(this).data('barcode'));
        });

        if (openBarcode) {
            openTicket(openBarcode);
        }
    });
    </script>
    @endpush
@endsection
