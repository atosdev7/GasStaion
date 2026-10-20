<?php

// Fixed fuel type identity, set once at business setup: [letter key => display name]. The key is
// what's actually stored everywhere a "fuel type" is referenced (tanks, nozzles, tickets, ...) —
// never a DB id, so renaming here is a real rename, and there is no "fuel types" list screen.
// Add a line here when a genuinely new fuel type is introduced — and see config/ticket_codes.php,
// which needs a code assigned for every (fuel type, spec) combination this fuel type sells tickets at.
return [
    'A' => 'Gasoline',
    'D' => 'Diesel',
];
