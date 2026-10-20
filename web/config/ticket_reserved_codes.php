<?php

// Named lookup for the reserved codes 21-25 (see App\Support\Tickets\Barcode::isReservedCode) — tickets
// the system generates itself rather than ones sold from published stock. Only 'direct' (spot
// sales) is used by the web app so far; the rest are reserved for when Smart210 creates them.
return [
    'direct' => '21',
    'self' => '22',
    'other' => '23',
    'ticket_loan' => '24',
    'remainder' => '25',
];
