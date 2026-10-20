<?php

return [
    // Opening balances
    'opening_tank_stock' => 'Opening tank stock',
    'opening_tank_hint' => 'Leave a tank blank if it has no opening stock.',
    'opening_customer_hint' => 'Opening debts and ticket loans appear on the Payments and Ticket Loans pages, and are paid or settled there like any other.',
    'opening_debts' => 'Opening customer debts',
    'opening_loans' => 'Opening ticket loans',
    'opening_delete_confirm' => 'Delete this opening balance?',
    'opening_debt_has_payments' => 'This opening debt already has payments, so it can no longer be deleted.',
    'opening_loan_has_settlements' => 'This opening loan already has settlements, so it can no longer be deleted.',
    'opening_remainders' => 'Opening remainder tickets',
    'opening_remainder_hint' => 'Remainder tickets that customers already hold when the program starts. Each one makes a new ticket (already sold) with no supply behind it. A backup never removes a remainder. A wrong entry is stopped by restricting its ticket (Ticket Management).',
    'opening_remainder_added' => 'Remainder ticket :barcode of :kg kg added.',

    // Field labels
    'fuel_type' => 'Fuel type',
    'tank_id' => 'Tank',
    'amount_kg' => 'Weight (kg)',
    'customer_id' => 'Customer',
    'payment_method' => 'Payment method',
    'comment' => 'Comment',
    'amount' => 'Amount',
    'barcode' => 'Barcode',
    'remainder_date' => 'Remainder date',

    // Labels and messages
    'saved' => 'Saved.',
    'created' => 'Created.',
    'deleted' => 'Deleted.',
    'payment_cash' => 'Cash',
    'payment_bank' => 'Bank',
    'new' => 'New',
    'delete' => 'Delete',
    'status' => 'Status',
    'ticket_status_publish' => 'Published',
    'ticket_status_sale' => 'Sold',
    'ticket_status_supply' => 'Supplied',
    'ticket_status_restrict' => 'Restricted',
    'save' => 'Save',

    // Copied from report.php
    'no_data' => 'No data for this period.',
];
