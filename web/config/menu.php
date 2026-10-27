<?php

/*
 * Sidebar groups. A group shows when at least one of its items is permitted; an item shows when the
 * user has its permission (null = everyone). 'single' => true renders the group as one plain link.
 * 'route' is a named route; items whose screen is not built yet link to '#'.
 * Labels are keys in resources/lang/*\/menu.php.
 */
return [
    [
        'label' => 'group_dashboard', 'icon' => 'fa-tachometer-alt', 'single' => true,
        'items' => [
            ['label' => 'home', 'route' => 'home', 'permission' => null],
        ],
    ],
    [
        'label' => 'group_fuel', 'icon' => 'fa-gas-pump',
        'items' => [
            ['label' => 'inbounds',  'route' => 'inbound.index',  'permission' => 'inbound.view'],
            ['label' => 'outbounds', 'route' => 'outbound.index', 'permission' => 'outbound.view'],
            ['label' => 'tank_stock', 'route' => 'tank-stock.index', 'permission' => 'outbound.view'],
        ],
    ],
    [
        'label' => 'group_tickets', 'icon' => 'fa-ticket-alt',
        'items' => [
            ['label' => 'ticket_inbound', 'route' => 'ticket-inbound.index', 'permission' => 'ticket_stock.view'],
            ['label' => 'ticket_publish', 'route' => 'ticket-publish.index', 'permission' => 'ticket_stock.view'],
            ['label' => 'ticket_lookup',  'route' => 'tickets.lookup',       'permission' => 'tickets.lookup'],
            ['label' => 'ticket_summary', 'route' => 'ticket-summary.index', 'permission' => 'ticket_stock.view'],
            ['label' => 'ticket_bulk',    'route' => 'ticket-bulk.index',    'permission' => 'balances.manage'],
        ],
    ],
    [
        'label' => 'group_cards', 'icon' => 'fa-id-card',
        'items' => [
            ['label' => 'cards',        'route' => 'cards.index',        'permission' => 'cards.view'],
            ['label' => 'card_topups',  'route' => 'card-topups.index',  'permission' => 'cards.view'],
            ['label' => 'card_summary', 'route' => 'card-summary.index', 'permission' => 'cards.view'],
        ],
    ],
    [
        'label' => 'group_sales', 'icon' => 'fa-cash-register',
        'items' => [
            ['label' => 'ticket_sales', 'route' => 'ticket-sales.index', 'permission' => 'ticket_sales.view'],
            ['label' => 'spot_sales',   'route' => 'spot-sales.index',   'permission' => 'spot_sales.view'],
            ['label' => 'card_sales',   'route' => 'card-sales.index',   'permission' => 'card_sales.view'],
            ['label' => 'customers',    'route' => 'customers.index',    'permission' => 'customers.view'],
        ],
    ],
    [
        'label' => 'group_supplements', 'icon' => 'fa-truck-loading',
        'items' => [
            ['label' => 'supplements', 'route' => 'supplements.index', 'permission' => 'supplements.view'],
            ['label' => 'remainders',  'route' => 'remainders.index',  'permission' => 'supplements.view'],
        ],
    ],
    [
        'label' => 'group_debts', 'icon' => 'fa-file-invoice-dollar',
        'items' => [
            ['label' => 'payments', 'route' => 'payments.index', 'permission' => 'payments.view'],
            ['label' => 'ticket_loans', 'route' => 'loans.index', 'permission' => 'loans.view'],
            ['label' => 'debt_summary', 'route' => 'debt-summary.index', 'permission' => 'payments.view'],
            ['label' => 'debt_aging', 'route' => 'debt-aging.index', 'permission' => 'payments.view'],
            ['label' => 'debt_statement', 'route' => 'debt-statement.show', 'permission' => 'payments.view'],
        ],
    ],
    [
        'label' => 'group_confirmations', 'icon' => 'fa-check-double',
        'items' => [
            ['label' => 'confirmation_overview',    'route' => 'confirmations.overview',    'permission' => 'confirmations.manage'],
            ['label' => 'confirmation_fuel',        'route' => 'confirmations.fuel',        'permission' => 'confirmations.manage'],
            ['label' => 'confirmation_tickets',     'route' => 'confirmations.tickets',     'permission' => 'confirmations.manage'],
            ['label' => 'confirmation_sales',       'route' => 'confirmations.index',       'permission' => 'confirmations.manage'],
            ['label' => 'confirmation_supplements', 'route' => 'confirmations.supplements', 'permission' => 'confirmations.manage'],
            ['label' => 'confirmation_debts',       'route' => 'confirmations.debts',       'permission' => 'confirmations.manage'],
            ['label' => 'confirmation_summary',     'route' => 'confirmations.summary',     'permission' => 'confirmations.manage'],
        ],
    ],
    [
        'label' => 'group_reports', 'icon' => 'fa-chart-bar',
        'items' => [
            ['label' => 'reports', 'route' => 'reports.index', 'permission' => 'reports.view'],
        ],
    ],
    [
        'label' => 'group_init', 'icon' => 'fa-sliders-h',
        'items' => [
            ['label' => 'tanks',        'route' => 'balances.tanks.index', 'permission' => 'balances.manage'],
            ['label' => 'init_debts',   'route' => 'balances.debts.index', 'permission' => 'balances.manage'],
            ['label' => 'ticket_loans', 'route' => 'balances.loans.index', 'permission' => 'balances.manage'],
            ['label' => 'init_ticket_sales', 'route' => 'balances.ticket-sales.index', 'permission' => 'balances.manage'],
            ['label' => 'init_remainders', 'route' => 'balances.remainders.index', 'permission' => 'balances.manage'],
        ],
    ],
    [
        'label' => 'group_users', 'icon' => 'fa-users', 'single' => true,
        'items' => [
            ['label' => 'users', 'route' => 'users.index', 'permission' => 'users.manage'],
        ],
    ],
    [
        'label' => 'group_admin', 'icon' => 'fa-cogs',
        'items' => [
            ['label' => 'devices', 'route' => 'devices.index', 'permission' => 'devices.view'],
            ['label' => 'fuel',    'route' => 'fuel.index',    'permission' => 'fuel.view'],
            ['label' => 'tanks',   'route' => 'tanks.index',   'permission' => 'fuel.view'],
            ['label' => 'nozzles', 'route' => 'nozzles.index', 'permission' => 'fuel.view'],
            ['label' => 'go_live', 'route' => 'balances.golive', 'permission' => 'balances.manage'],
            ['label' => 'sync',    'route' => 'sync.index',    'permission' => 'sync.view'],
            ['label' => 'backup',  'route' => 'backup.index',  'permission' => 'backup.manage'],
            ['label' => 'audit',   'route' => 'audit.index',   'permission' => 'audit.view'],
        ],
    ],
];
