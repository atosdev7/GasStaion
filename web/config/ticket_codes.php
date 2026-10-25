<?php

// Ticket barcode `code` (2 digits) per (fuel type, spec) — a fixed, arbitrary, business-assigned
// number. NOT derived from the fuel type or the spec by any formula; assigned once at business
// setup and looked up directly. Add an entry here whenever a fuel type starts selling tickets at
// a spec it didn't before. Every code must be a plain 2-digit string outside 21-25 (reserved for
// tickets the Smart210 creates itself — direct/self/other/ticket_loan/remainder, see App\Support\Tickets\Barcode).
//
// Keyed by config/fuel_types.php's letter, then by the spec key (config/ticket_specs.php).
return [
    'A' => [0 => '03', 1 => '04', 2 => '01', 3 => '08'], // Gasoline: 5kg=03, 10kg=04, 15kg=01, 50kg=08
    'D' => [0 => '06', 1 => '07', 2 => '02', 3 => '05'], // Diesel: 5kg=06, 10kg=07, 15kg=02, 50kg=05
];
