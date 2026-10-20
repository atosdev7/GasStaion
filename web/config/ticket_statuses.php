<?php

// Ticket lifecycle status, stored as a small int rather than varchar — tickets are queried by
// status frequently (e.g. the tickets_status_fuel_type index), and an int comparison/index is
// cheaper than a string one. Never store the raw number in code; always go through this config
// (e.g. config('ticket_statuses.publish')) so the mapping stays in one place.
return [
    'publish' => 0,
    'sale' => 1,
    'supply' => 2,
    'restrict' => 3,
];
