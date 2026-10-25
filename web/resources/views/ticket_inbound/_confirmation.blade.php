{{-- The state of a record of the blank ticket stock ($row: an inbound or a publish; $date: its day). Whoever may confirm gets a link to the daily
     confirmation of that day (the Ticket stock page). --}}
@php($badge = $row->isConfirmed()
    ? '<span class="badge badge-success">' . e(__('tickets.confirmed')) . '</span>'
    : '<span class="badge badge-secondary">' . e(__('tickets.not_confirmed')) . '</span>')
@can('confirmations.manage')
    <a href="{{ route('confirmations.tickets', ['date' => substr((string) $date, 0, 10)]) }}" title="{{ __('tickets.go_to_confirmation') }}">{!! $badge !!} <i class="fas fa-external-link-alt fa-xs"></i></a>
@else
    {!! $badge !!}
@endcan
