<?php

/*
 * Role => permissions. "x.manage" implies "x.view". '*' = everything.
 */
return [

    'roles' => [
        'admin' => ['*'],

        'manager' => [
            'devices.view',
            'fuel.manage',
            'customers.manage',
            'inbound.manage',
            'outbound.manage',
            'costs.view',          // the cost per kg of a sale (the assistant and the cashier never see it)
            'ticket_stock.manage',
            'ticket_sales.manage',
            'payments.view',
            'tickets.restrict',
            'tickets.lookup',
            'balances.manage',
            'sync.manage',
            'confirmations.manage',
            'sales.delete',
            'sales.edit',          // the date, comment and receipt of a ticket or spot sale
            'card_sales.edit',     // the same for a card sale (a top-up)
            'confirmations.unconfirm', // take a confirmation back, so that the record can be edited again (manager only)
            'sales.return',
            'reports.view',
            'dashboard.view',
            'audit.view',
            'supplements.manage',
            'loans.view',
            'cards.manage',        // unlock, block / unblock a lost card, reset a PIN
            'cards.topup',         // top up an existing card on the web
            'card_sales.delete',   // delete a card sale (cashier, assistant and accountant cannot)
        ],

        'accountant' => [
            'fuel.view',
            'customers.manage',
            'inbound.manage',
            'outbound.manage',
            'costs.view',
            'ticket_stock.manage', // adds, changes and deletes ticket inbounds and publishes blank tickets, like the assistant
            'ticket_sales.manage',
            'payments.manage',
            'tickets.restrict',
            'tickets.lookup',
            'balances.manage',
            'sync.manage',
            'confirmations.manage',
            'sales.delete',
            'sales.edit',
            'card_sales.edit',
            'sales.return',
            'reports.view',
            'dashboard.view',
            'supplements.manage',
            'loans.manage',
            'cards.manage',
        ],

        'assistant' => [
            'customers.view',
            'ticket_stock.manage',
            'ticket_sales.manage',
            'supplements.manage',
            'tickets.lookup',
            'sync.manage',
            'sales.delete',
            'sales.edit',
            'sales.return',
        ],

        'cashier' => [
            'customers.view',
            'tickets.lookup',
        ],
    ],

    // Roles allowed to sign in on the Smart210 (cashier terminal): the cashiers only.
    'pos_roles' => ['cashier'],
];
