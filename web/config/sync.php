<?php

return [
    // The Smart210 works offline and the cashier uploads its records once a day at check time (downloading is automatic
    // and does not count). One that has not uploaded for this many hours is flagged on the Sync Status screen. Staff
    // check it before restricting a ticket: an out-of-date Smart210 could still sell or supply that ticket.
    'stale_hours' => 30,

    // Requests a minute that one Smart210 (one address) may make to the device API. A first download of a station with 50000 tickets needs about 200
    // requests, so this is set higher there (API_THROTTLE_PER_MINUTE in .env); the end-to-end tests send far more and raise it a lot.
    'throttle_per_minute' => (int) env('API_THROTTLE_PER_MINUTE', 60),
];
