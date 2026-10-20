<?php

// The 5-digit "sign" is one fixed constant for the whole business — it never varies per
// ticket, fuel type or spec, so it is not entered anywhere in the app, only set here once.
// The real value is 86761 (user, 2026-10-12). The tests and the end-to-end run use TICKET_SIGN=00000
// (phpunit.xml, e2e/run.ps1), so they do not depend on it.
return env('TICKET_SIGN', '86761');
