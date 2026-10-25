<?php

return [
    // Ticket inbound
    'blank_inbound' => 'Blank ticket inbound',
    'spec_hint' => 'The cylinder weight this ticket is for.',
    'ticket_inbound_delete_confirm' => 'Delete this blank ticket inbound record? The blank ticket stock goes down by its quantity.',
    'quantity_hint' => 'A negative number takes blank tickets out of the stock (for example the ones spoiled in printing).',
    'confirmation' => 'Confirmation',
    'confirmed' => 'Confirmed',
    'not_confirmed' => 'Not confirmed',
    'confirmed_locked' => 'This record is confirmed and cannot be changed or deleted. A manager can take the confirmation back.',
    'publish_confirm' => "Publish :count tickets (:item), serial :start to :end?

A published batch cannot be reversed: it cannot be changed or deleted.",
    'ticket_inbound_below_published' => 'Not possible: fewer blank tickets would be received than were published already for this fuel type and spec.',
    'ticket_inbound_fixed_hint' => 'The fuel type and the spec of a record do not change: the published batches are numbered for them.',

    // Shared in tickets
    'publish' => 'Publish tickets',
    'view_tickets' => 'Tickets',

    // Ticket publish
    'blank_stock' => 'Blank ticket stock',
    'recent_batches' => 'Published batches',
    'no_blank_stock' => 'There are no blank tickets to publish. Record a blank ticket inbound first.',
    'stock_item_hint' => 'The code is filled in automatically from the fuel type\'s code for this spec.',
    'serial_range_hint' => 'The serial range printed on this batch (the sign and code are automatic).',
    'code_not_configured' => 'This fuel type has no ticket code configured for this spec yet.',
    'not_enough_stock' => 'There are not enough blank tickets of this spec.',
    'range_backwards' => 'The end serial must not be before the start serial.',
    'range_too_large' => 'A single batch cannot exceed :max tickets.',
    'serial_overlap' => 'These serial numbers overlap tickets that already exist.',
    'published' => 'Published :count tickets: :start to :end.',
    'batch_tickets' => 'Tickets of the batch',
    'ticket_downloaded' => 'Downloaded by a terminal',

    // Ticket lookup
    'ticket_not_found' => 'No ticket found with that barcode.',
    'restrict_ticket' => 'Restrict ticket',
    'lift_restriction' => 'Lift restriction',
    'restrict_confirm' => 'Restrict this ticket? It can no longer be sold, supplied or used to settle a loan until the restriction is lifted.',
    'ticket_restricted' => 'Ticket restricted.',
    'restriction_lifted' => 'Restriction lifted.',
    'ticket_not_restrictable' => 'Only a published or sold ticket can be restricted.',
    'ticket_not_restricted' => 'This ticket is not restricted.',
    'tickets_table' => 'Tickets',
    'all_specs' => 'All specs',
    'ticket_publish_date' => 'Published',
    'ticket_details' => 'Details',
    'ticket_restrict' => 'Restrict',
    'ticket_lift' => 'Lift',
    'lift_confirm' => 'Lift the restriction of this ticket?',

    // Ticket inbound

    // Ticket publish
    'available' => 'Available',
    'serial' => 'Serial',

    // Shared in tickets
    'code' => 'Code (2 digits)',

    // Ticket lookup
    'sold_at' => 'Sold at',

    // Field labels
    'fuel_type' => 'Fuel type',
    'inbound_date' => 'Inbound date',
    'amount_kg' => 'Weight (kg)',
    'note' => 'Note',
    'spec' => 'Spec (cylinder weight)',
    'kg' => 'kg',
    'quantity' => 'Count',
    'start_serial' => 'Start serial',
    'end_serial' => 'End serial',
    'sale_date' => 'Sale date',
    'customer_id' => 'Customer',
    'usage' => 'Usage',
    'payment_method' => 'Payment method',
    'total_amount' => 'Total amount',
    'sale_type' => 'Sale type',
    'barcode' => 'Barcode',
    'nozzle_id' => 'Nozzle',
    'supplied_at' => 'Supplied at',
    'out_amount_kg' => 'Out amount (kg)',
    'out_amount_lt' => 'Out amount (L)',
    'date' => 'Date',

    // Labels and messages
    'saved' => 'Saved.',
    'created' => 'Created.',
    'deleted' => 'Deleted.',
    'settled_loan' => 'Settled :kg kg against the loan.',
    'payment_cash' => 'Cash',
    'payment_bank' => 'Bank',
    'summary_in' => 'Inbound',
    'summary_balance' => 'Balance',
    'summary_publish' => 'Publish',
    'summary_count' => 'Count',
    'summary_start' => 'Start',
    'summary_end' => 'End',
    'sale_type_ticket' => 'Ticket',
    'sale_type_spot' => 'Spot',
    'sale_type_card' => 'Card',
    'usage_sale' => 'Sale',
    'usage_self' => 'Self use',
    'usage_other' => 'Other (given away)',
    'usage_ticket_loan' => 'Ticket loan',
    'new' => 'New',
    'delete' => 'Delete',
    'status' => 'Status',
    'ticket_status_publish' => 'Published',
    'ticket_status_sale' => 'Sold',
    'ticket_status_supply' => 'Supplied',
    'ticket_status_restrict' => 'Restricted',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'edit' => 'Edit',
    'actions' => 'Actions',
    'all_fuel_types' => 'All fuel types',
    'clear_filters' => 'Clear',
    'all_statuses' => 'All states',
    'barcode_contains' => 'Barcode contains',

    // Copied from sale_delete.php
    'ticket_sale_deleted' => 'The sale of this ticket was deleted, so its restriction cannot be lifted.',

    // Copied from report.php
    'show' => 'Show',
    'no_data' => 'No data for this period.',
    'export_xls' => 'Export XLS',
    'total' => 'Total',
];
