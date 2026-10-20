#ifndef TICKETSTATUS_H
#define TICKETSTATUS_H

// tickets.status, the same numbers as the web app's config/ticket_statuses.php.
namespace TicketStatus {
enum Status {
    Publish = 0,  // in stock, can be sold
    Sale = 1,     // sold, not supplied yet
    Supply = 2,   // fuel was dispensed for it
    Restrict = 3  // cannot be used any more
};
}

#endif
