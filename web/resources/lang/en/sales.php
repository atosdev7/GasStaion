<?php

return [
    // Ticket sales
    'recent_sales' => 'Recent sales',
    'price_above_zero' => 'The price of a sale must be above 0.',
    'no_sale_stock' => 'There are no published tickets to sell yet. Publish tickets first.',
    'ticket_sale' => 'Ticket sale',
    'ticket_lines' => 'Tickets to sell',
    'add_item' => 'Add item',
    'sale_not_found' => 'The sale was not found.',
    'check_item' => 'Check',
    'item_valid' => 'Valid: serials :start to :end can be sold.',
    'item_not_valid' => 'Not valid: some tickets from :start to :end are not available to sell.',
    'sale_items_empty' => 'No items yet. Add an item on the left.',
    'fifo_placeholder' => 'lowest available',

    // Shared in sales
    'not_enough_sale_stock' => 'There are not enough published tickets of this spec to sell.',
    'spot_sale' => 'Spot sale',
    'receipt_issued' => 'Receipt issued',

    // Shared by the sales screens
    'sold' => 'Sold :count tickets, total :total.',
    'sold_spot' => 'Sold :kg kg, total :total.',
    'sale_items' => 'Items',
    'sale_made' => 'Made',
    'customer_search' => 'Type a customer name',
    'customer_add_new' => 'Add ":name" as a new customer',
    'customer_new_note' => 'A new customer will be added when the sale is saved.',
    'customer_cannot_add' => 'You are not allowed to add customers.',

    // Spot sales
    'spot_sale_cash_hint' => 'Spot sales are always paid in cash.',
    'all_fuels' => 'All fuel types',
    'all_usages' => 'All usages',
    'sale_date_too_old' => 'A sale cannot be dated more than :days days back. Ask the manager.',
    'paid_more_than_total' => 'The paid amount :paid is more than the total :total: only :total was recorded. Check the value you typed.',
    'paid_more_warning' => 'The paid amount is more than the total: it could be a wrong value (only the total is recorded).',

    // Customers
    'phone' => 'Phone',

    // Shared in sales
    'receipt' => 'Receipt',

    // Field labels
    'price' => 'Price per kg',
    'fuel_type' => 'Fuel type',
    'amount_kg' => 'Weight (kg)',
    'cost' => 'Cost per kg',
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
    'paid_amount' => 'Paid amount',
    'unpaid_amount' => 'Unpaid amount',
    'comment' => 'Comment',
    'sale_type' => 'Sale type',
    'ticket_barcodes' => 'Ticket barcodes',
    'tickets' => 'Tickets',
    'name' => 'Name',

    // Labels and messages
    'saved' => 'Saved.',
    'created' => 'Created.',
    'deleted' => 'Deleted.',
    'payment_cash' => 'Cash',
    'payment_bank' => 'Bank',
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
    'save' => 'Save',
    'cancel' => 'Cancel',
    'edit' => 'Edit',
    'actions' => 'Actions',
    'clear_filters' => 'Clear',
    'sale_view' => 'View',
    'sale_details' => 'Sale details',
    'all_customers' => 'All customers',
    'back_to_list' => 'Back to the list',
    'sale_confirmed' => 'Confirmed',
    'restored' => 'Restored.',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'activate' => 'Activate',
    'deactivate' => 'Deactivate',
    'restore' => 'Restore',
    'delete_confirm' => 'Delete this record? It will disappear from lists, but nothing that already refers to it is affected. It can be restored from the trash.',
    'show_trash' => 'Show deleted',
    'show_active' => 'Back to list',

    // Copied from report.php
    'show' => 'Show',
    'no_data' => 'No data for this period.',

    // Copied from cards.php
    'cards_deleted' => 'The top-up is deleted.',
    'blocked_confirmed' => 'It is confirmed and can no longer be deleted.',
    'blocked_used' => 'The card was used since: its balance would go below zero.',
    'holder' => 'Customer name',
    'uid' => 'Card ID',
    'by' => 'By',
    'all_cards' => 'All cards',
    'cards_delete_confirm' => 'Delete this top-up?',
];
