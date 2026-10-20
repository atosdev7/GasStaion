<?php

return [
    // A sale can be deleted (for wrong input) only this many days after it was made, and only until the accountant
    // confirms it. After that, a return entry is the way to correct it.
    'delete_days' => (int) env('SALE_DELETE_DAYS', 3),
];
