<?php

return [
    // Debt payments
    'debt_view_sale' => 'View sale',
    'all_payment_methods' => 'All payment methods',
    'debts' => 'Debts',
    'total_owed' => 'Total owed',
    'unallocated_payments' => 'Unallocated payments',
    'unallocated_payments_hint' => 'Received but not yet matched to a specific debt.',
    'record_payment' => 'Record payment',
    'apply_to_debt' => 'Apply to',
    'apply_to_debt_hint' => 'Leave as general payment if it\'s not clear which of the customer\'s debts this pays down.',
    'general_payment_option' => 'General payment (not tied to a specific debt)',
    'debt_customer_mismatch' => 'That debt does not belong to the selected customer.',

    // Ticket loans
    'total_loaned' => 'Total loaned',
    'record_settlement' => 'Record settlement',
    'apply_to_loan' => 'Apply to loan',
    'no_open_loans' => 'There are no open ticket loans to settle.',
    'ticket_barcode_hint' => 'Scan or type the barcode of the ticket the customer is surrendering.',
    'ticket_not_settleable' => 'That barcode isn\'t a sold ticket that can be surrendered (already used, never sold, or doesn\'t exist).',
    'ticket_fuel_type_mismatch' => 'That ticket\'s fuel type doesn\'t match the loan\'s.',

    // Shared in debts
    'total_settled' => 'Total settled',
    'total_balance' => 'Total balance',
    'debt_settled' => 'Settled',
    'debt_open' => 'Open',

    // Ticket loans
    'ticket_barcode' => 'Ticket barcode',

    // Field labels
    'fuel_type' => 'Fuel type',
    'amount_kg' => 'Weight (kg)',
    'note' => 'Note',
    'sale_date' => 'Sale date',
    'customer_id' => 'Customer',
    'payment_method' => 'Payment method',
    'amount' => 'Amount',
    'settled_amount' => 'Settled amount',
    'balance' => 'Balance',
    'paid_date' => 'Paid date',
    'loaned_at' => 'Loaned at',

    // Labels and messages
    'created' => 'Created.',
    'settled_loan' => 'Settled :kg kg against the loan.',
    'payment_cash' => 'Cash',
    'payment_bank' => 'Bank',
    'opening_balance' => 'Opening balance',
    'status' => 'Status',
    'cancel' => 'Cancel',
    'actions' => 'Actions',
    'all_fuel_types' => 'All fuel types',
    'clear_filters' => 'Clear',
    'all_customers' => 'All customers',
    'all_statuses' => 'All states',

    // Copied from report.php
    'show' => 'Show',
    'no_data' => 'No data for this period.',
    'from' => 'From',
    'to' => 'To',
    'export_xls' => 'Export XLS',
    'total' => 'Total',
];
