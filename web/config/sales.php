<?php

return [
    // How many days back a sale may be dated when it is entered or when its date is changed (not for the manager and the admin, who have
    // sales.backdate). Deleting a sale has no time limit of its own: it is refused as soon as a ticket moved on, the day is confirmed and so on.
    'backdate_days' => (int) env('SALE_BACKDATE_DAYS', 3),
];
