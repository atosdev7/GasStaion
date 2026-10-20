<?php

// Tickets with a reserved code (21-25: direct, self, other, ticket loan, remainder) are minted by both the web app and
// the Smart210. The 9-digit serial space is split so their barcodes can never collide: the web app numbers below this
// value, a Smart210 numbers from this value + 1 upwards.
return [
    'device_base' => 500000000,
];
