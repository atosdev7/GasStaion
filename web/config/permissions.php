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
            'sales.all',           // sees and works on every kind of sale (the assistant on every ticket sale; the cashier only looks at his own)
            'spot_sales.manage',   // spot sales (the assistant works on ticket sales only)
            'card_sales.view',     // the card sales (top-ups) list
            'sales.backdate',      // dates a sale further back than config('sales.backdate_days') (the admin has everything)
            'ticket_stock.manage',
            'ticket_sales.manage',
            'payments.manage',     // records and reverses debt payments (the manager can do everything the accountant and the assistant can)
            'tickets.restrict',
            'tickets.lookup',
            'balances.manage',
            'users.manage',        // the accounts of the staff (not the admin's: see UserController)
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
            'supplements.view',
            'supplements.comment', // looks at the supplies and writes the comment of one (the supplies are made on the Smart210; only the admin creates, changes and deletes them here)
            'loans.manage',        // settles the ticket loans and reverses a settlement
            'cards.manage',        // unlock, block / unblock a lost card, reset a PIN
            'cards.topup',         // top up an existing card on the web
            'cards.free',          // set a registered card as a free payment card (top-ups with a price of 0), or take that away (the manager and the admin)
            'card_sales.delete',   // delete a card sale (cashier, assistant and accountant cannot)
        ],

        'accountant' => [
            'fuel.manage',         // sets the price of a fuel type (the default price of a sale), like the manager
            'customers.manage',
            'inbound.manage',
            'outbound.manage',
            'costs.view',
            'sales.all',
            'spot_sales.manage',
            'card_sales.view',
            'cards.topup',         // (the accountant tops up and edits, but does not delete a card sale: that is the manager's and the admin's)
            'ticket_stock.manage', // adds, changes and deletes ticket inbounds and publishes blank tickets, like the assistant
            'ticket_sales.manage',
            'payments.manage',
            'tickets.restrict',
            'tickets.lookup',
            'balances.manage',
            'sync.manage',
            'confirmations.manage',
            'confirmations.unconfirm', // takes a confirmation back, like the manager
            'sales.delete',
            'sales.edit',
            'card_sales.edit',
            'sales.return',
            'reports.view',
            'dashboard.view',
            'supplements.view',
            'supplements.comment',
            'loans.manage',
            'cards.manage',
        ],

        'assistant' => [
            'customers.view',
            'ticket_stock.manage',
            'ticket_sales.manage',
            'payments.manage',     // records and reverses debt payments, like the accountant
            'loans.manage',        // settles the ticket loans and reverses a settlement, like the accountant
            'tickets.lookup',
            'sales.delete',
            'sales.edit',
            'sales.return',
        ],

        // the cashier works on the Smart210; on the web he only looks at the sales he issued himself (spot, ticket and card sales); he has no
        // Ticket Management (not even the lookup)
        'cashier' => [
            'customers.view',
            'ticket_sales.view',
            'spot_sales.view',
            'card_sales.view',
            'supplements.view',    // looks at the supplies and remainders; changes nothing here (it all happens on the Smart210)
            'loans.view',          // looks at the ticket loans (not at the money debts); changes nothing
        ],
    ],

    // Roles allowed to sign in on the Smart210 (cashier terminal): the cashiers only.
    'pos_roles' => ['cashier'],
];
