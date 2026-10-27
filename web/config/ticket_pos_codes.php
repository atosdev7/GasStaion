<?php

// The 2-digit `code` of a ticket a POS issues itself (the Smart210, or the web app for a sale entered there) — NOT a press ticket (their codes are in
// config/ticket_codes.php and start with 0). A barcode is 5 digits of sign + 2 digits of code + 9 digits of serial; the serial counts per code.
//
// The code is two digits: the FIRST is the fuel type, the SECOND is the kind of the ticket:
//   first digit   1 gasoline, 2 diesel                          (keyed by config/fuel_types.php's letter)
//   second digit  1 spot sale, 2 self use, 3 other use, 4 ticket loan, 5 card, 9 remainder
// so 21 is a diesel spot sale, 15 a gasoline ticket made for a card, 29 a diesel remainder. See App\Support\Tickets\Barcode.
return [
    'fuel' => ['A' => '1', 'D' => '2'],
    'type' => ['direct' => '1', 'self' => '2', 'other' => '3', 'ticket_loan' => '4', 'card' => '5', 'remainder' => '9'],
];
