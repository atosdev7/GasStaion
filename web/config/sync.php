<?php

return [
    // The Smart210 works offline and the cashier uploads its records once a day at check time (downloading is automatic
    // and does not count). One that has not uploaded for this many hours is flagged on the Sync Status screen. Staff
    // check it before restricting a ticket: an out-of-date Smart210 could still sell or supply that ticket.
    'stale_hours' => 30,
];
