<?php

return [
    // Fuel inbound
    'default' => 'Default',
    'make_default' => 'Make default',
    'default_hint' => 'The default cost pre-fills the cost field on sales forms for this fuel type. The first inbound of a fuel type is the default automatically.',
    'inbound_delete_confirm' => 'Delete this fuel inbound record? The tank stock goes down by its amount.',
    'inbound_default_delete' => 'The default record of a fuel type cannot be deleted while other records of that fuel type exist. Make another record the default first.',
    'inbound_tank_fuel' => 'Choose a tank that holds the same fuel type as this record.',
    'inbound_cost_hint' => 'Sales that were already made keep the cost they were made with.',

    // Fuel outbound
    'outbound_per_tank' => 'Dispensed per tank',
    'all_tanks' => 'All tanks',

    // Fuel inbound
    'is_default' => 'Default cost for sales',

    // Field labels
    'fuel_type' => 'Fuel type',
    'tank_id' => 'Tank',
    'inbound_date' => 'Inbound date',
    'amount_kg' => 'Weight (kg)',
    'cost' => 'Cost per kg',
    'note' => 'Note',
    'nozzle_id' => 'Nozzle',
    'supplied_at' => 'Supplied at',
    'mode' => 'Mode',

    // Labels and messages
    'saved' => 'Saved.',
    'created' => 'Created.',
    'deleted' => 'Deleted.',
    'mode_auto' => 'Auto',
    'mode_manual' => 'Manual',
    'mode_hint' => 'Auto: the system sets the amount and starts the fill. Manual: the supplier types the amount at the dispenser.',
    'new' => 'New',
    'delete' => 'Delete',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'edit' => 'Edit',
    'actions' => 'Actions',

    // Copied from report.php
    'from' => 'From',
    'to' => 'To',
    'show' => 'Show',
    'count' => 'Count',
    'liters' => 'Litres',
    'no_data' => 'No data for this period.',
    'total' => 'Total',
    'from_name' => 'From',
];
