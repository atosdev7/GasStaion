<?php

return [
    // Remainders
    'add_remainder' => 'Add remainder',
    'no_supply_for_remainder' => 'There is no supply that can still give a remainder: every supply either has one or used all its tickets.',
    'remainder_room' => 'Left: :kg kg',
    'remainder_supply_hint' => 'Only a supply that gave less than its tickets covered can have a remainder; the weight is at most what was not dispensed.',
    'remainder_exists' => 'This supply already has a remainder.',
    'remainder_too_much' => 'The weight can be at most :kg kg (what the supply did not dispense).',
    'remainder_added' => 'Remainder ticket :barcode of :kg kg added.',
    'remainders_total_kg' => 'Remainder tickets, total (kg)',
    'remainder_opening' => 'Opening remainder (no supply)',
    'remainder_supply_cleared' => 'Supply cleared by a backup',
    'remainder_comment_saved' => 'The comment was saved.',

    // Supplements
    'supply_density_hint' => 'Leave it as filled in (the density of the fuel type) unless this supply was converted with another one.',
    'supplement_details' => 'Supply details',
    'supplement_not_found' => 'The supply was not found.',
    'supply_tickets' => 'Tickets used',
    'all_nozzles' => 'All nozzles',
    'all_modes' => 'All modes',
    'sale_tickets_first' => 'Only the first :count tickets are listed.',
    'record_supplement' => 'Record supplement',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'delete_confirm' => 'Delete this supply? Its tickets are sold again and its remainder ticket, if it made one, can no longer be used.',
    'deleted' => 'The supply was deleted: its tickets are sold again.',
    'saved' => 'Saved.',
    'saved_with_remainder' => 'Saved. Remainder ticket :barcode for :left kg was created.',
    'edit_title' => 'Supply',
    'edit_hint_comment' => 'You can write the comment. The supply is made on the Smart210; when it is wrong, the cashier corrects it there after the confirmation was taken back.',
    'edit_hint_full' => 'The admin changes the nozzle (of the same fuel type), the time, the mode, the litres and the comment. A supply that made a remainder ticket or was paid with a card keeps its litres: delete it and make it again.',
    'blocked_confirmed' => 'This supply is confirmed. A manager takes the confirmation back first.',
    'blocked_remainder_used' => 'Its remainder ticket was already used, so the supply cannot be deleted.',
    'litres_fixed' => 'The litres cannot be changed: the supply has a remainder ticket or was paid with a card.',
    'total_dispensed_kg' => 'Total dispensed (kg)',
    'total_dispensed_liters' => 'Total dispensed (L)',
    'supplement_barcode_hint' => 'Scan or type the barcodes of the tickets the customer is using at the pump, one per line.',
    'nozzle_fuel_type_mismatch' => 'That ticket\'s fuel type doesn\'t match the nozzle\'s.',
    'density_not_configured' => 'This fuel type has no density configured yet, so litres can\'t be converted to kg.',
    'supplied' => 'Supplied :kg kg.',
    'supplied_with_remainder' => 'Supplied :kg kg. Remainder ticket :barcode for :left kg was created.',
    'supplied_more_than_tickets' => 'That is more than the tickets cover (:preset kg).',
    'tickets_not_suppliable' => 'These barcodes aren\'t sold tickets that can be supplied (already used, restricted, never sold, or don\'t exist): :barcodes',

    // Supplements
    'liter' => 'L',
    'supply_date' => 'Supply date',
    'out_amount' => 'Out amount (kg / L)',
    'preset_kg' => 'Tickets total (kg)',

    // Field labels
    'density' => 'Density (kg/L)',
    'fuel_type' => 'Fuel type',
    'amount_kg' => 'Weight (kg)',
    'kg' => 'kg',
    'comment' => 'Comment',
    'barcode' => 'Barcode',
    'nozzle_id' => 'Nozzle',
    'supplied_at' => 'Supplied at',
    'mode' => 'Mode',
    'out_amount_lt' => 'Out amount (L)',
    'ticket_barcodes' => 'Ticket barcodes',
    'remainder' => 'Remainder',
    'remainder_date' => 'Remainder date',
    'complete_date' => 'Complete date',

    // Labels and messages
    'remainder_of_supply' => 'From supply',
    'mode_auto' => 'Auto',
    'mode_manual' => 'Manual',
    'mode_hint' => 'Auto: the system sets the amount and starts the fill. Manual: the supplier types the amount at the dispenser.',
    'status' => 'Status',
    'ticket_status_publish' => 'Published',
    'ticket_status_sale' => 'Sold',
    'ticket_status_supply' => 'Supplied',
    'ticket_status_restrict' => 'Restricted',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'actions' => 'Actions',
    'all_fuel_types' => 'All fuel types',
    'clear_filters' => 'Clear',
    'sale_view' => 'View',
    'barcode_contains' => 'Barcode contains',
    'back_to_list' => 'Back to the list',
    'sale_confirmed' => 'Confirmed',

    // Copied from sale_delete.php
    'deleted_at' => 'Deleted at',

    // Copied from cards.php
    'card' => 'Card',

    // Copied from report.php
    'show' => 'Show',
    'no_data' => 'No data for this period.',
];
