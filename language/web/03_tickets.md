# Tickets

Sidebar group: `menu.group_tickets` = Ticket Management

Each key is shown with its English text (the web app is English only). A key is listed under every page whose code uses it, so shared keys repeat. **MISSING** = absent in that language.

## Ticket inbound

Menu key: `menu.ticket_inbound`  
Files: `app/Http/Controllers/TicketInboundController.php`, `resources/views/ticket_inbound/*`

| Key | en |
|---|---|
| `menu.ticket_inbound` | Ticket Inbound |
| `tickets.actions` | Actions |
| `tickets.blank_inbound` | Blank ticket inbound |
| `tickets.cancel` | Cancel |
| `tickets.created` | Created. |
| `tickets.delete` | Delete |
| `tickets.deleted` | Deleted. |
| `tickets.edit` | Edit |
| `tickets.from_name` | From |
| `tickets.fuel_type` | Fuel type |
| `tickets.inbound_date` | Inbound date |
| `tickets.kg` | kg |
| `tickets.new` | New |
| `tickets.note` | Note |
| `tickets.quantity` | Count |
| `tickets.save` | Save |
| `tickets.saved` | Saved. |
| `tickets.spec` | Spec (cylinder weight) |
| `tickets.spec_hint` | The cylinder weight this ticket is for. |
| `tickets.ticket_inbound_below_published` | Not possible: fewer blank tickets would be received than were published already for this fuel type and spec. |
| `tickets.ticket_inbound_delete_confirm` | Delete this blank ticket inbound record? The blank ticket stock goes down by its quantity. |
| `tickets.ticket_inbound_fixed_hint` | The fuel type and the spec of a record do not change: the published batches are numbered for them. |

## Ticket publish

Menu key: `menu.ticket_publish`  
Files: `app/Http/Controllers/TicketPublishController.php`, `resources/views/ticket_publish/*`, `app/Support/Barcode.php`, `app/Support/SerialCounter.php`

| Key | en |
|---|---|
| `menu.ticket_publish` | Ticket Publish |
| `tickets.actions` | Actions |
| `tickets.available` | Available |
| `tickets.barcode` | Barcode |
| `tickets.batch_delete_confirm` | Delete this batch? Its blank tickets are removed and go back to the blank stock, and its serial numbers are free again. |
| `tickets.batch_downloaded` | Not possible: a terminal has already downloaded the tickets of this batch. |
| `tickets.batch_edit_hint` | The fuel type, the spec and the code of a batch do not change. A new serial range numbers its blank tickets again. |
| `tickets.batch_tickets` | Tickets of the batch |
| `tickets.batch_used` | Not possible: some tickets of this batch were already sold, supplied or restricted. |
| `tickets.blank_stock` | Blank ticket stock |
| `tickets.cancel` | Cancel |
| `tickets.code` | Code (2 digits) |
| `tickets.code_not_configured` | This fuel type has no ticket code configured for this spec yet. |
| `tickets.date` | Date |
| `tickets.delete` | Delete |
| `tickets.deleted` | Deleted. |
| `tickets.edit` | Edit |
| `tickets.end_serial` | End serial |
| `tickets.fuel_type` | Fuel type |
| `tickets.kg` | kg |
| `tickets.no_blank_stock` | There are no blank tickets to publish. Record a blank ticket inbound first. |
| `tickets.not_enough_stock` | There are not enough blank tickets of this spec. |
| `tickets.publish` | Publish tickets |
| `tickets.published` | Published :count tickets: :start to :end. |
| `tickets.quantity` | Count |
| `tickets.range_backwards` | The end serial must not be before the start serial. |
| `tickets.range_too_large` | A single batch cannot exceed :max tickets. |
| `tickets.recent_batches` | Published batches |
| `tickets.save` | Save |
| `tickets.saved` | Saved. |
| `tickets.serial` | Serial |
| `tickets.serial_overlap` | These serial numbers overlap tickets that already exist. |
| `tickets.serial_range_hint` | The serial range printed on this batch (the sign and code are automatic). |
| `tickets.spec` | Spec (cylinder weight) |
| `tickets.start_serial` | Start serial |
| `tickets.status` | Status |
| `tickets.stock_item_hint` | The code is filled in automatically from the fuel type's code for this spec. |
| `tickets.ticket_downloaded` | Downloaded by a terminal |
| `tickets.view_tickets` | Tickets |

## Ticket lookup (with restrict / lift)

Menu key: `menu.ticket_lookup`  
Files: `app/Http/Controllers/TicketLookupController.php`, `app/Http/Controllers/TicketRestrictController.php`, `resources/views/ticket_lookup/*`

| Key | en |
|---|---|
| `menu.supplements` | Supplements |
| `menu.ticket_loans` | Ticket Loans |
| `menu.ticket_lookup` | Ticket Lookup |
| `menu.ticket_sales` | Ticket Sales |
| `tickets.actions` | Actions |
| `tickets.all_fuel_types` | All fuel types |
| `tickets.all_specs` | All specs |
| `tickets.all_statuses` | All states |
| `tickets.amount_kg` | Weight (kg) |
| `tickets.barcode` | Barcode |
| `tickets.barcode_contains` | Barcode contains |
| `tickets.clear_filters` | Clear |
| `tickets.code` | Code (2 digits) |
| `tickets.customer_id` | Customer |
| `tickets.fuel_type` | Fuel type |
| `tickets.kg` | kg |
| `tickets.lift_confirm` | Lift the restriction of this ticket? |
| `tickets.lift_restriction` | Lift restriction |
| `tickets.no_data` | No data for this period. |
| `tickets.nozzle_id` | Nozzle |
| `tickets.out_amount_kg` | Out amount (kg) |
| `tickets.out_amount_lt` | Out amount (L) |
| `tickets.payment_method` | Payment method |
| `tickets.publish` | Publish tickets |
| `tickets.restrict_confirm` | Restrict this ticket? It can no longer be sold, supplied or used to settle a loan until the restriction is lifted. |
| `tickets.restrict_ticket` | Restrict ticket |
| `tickets.restriction_lifted` | Restriction lifted. |
| `tickets.sale_date` | Sale date |
| `tickets.sale_type` | Sale type |
| `tickets.settled_loan` | Settled :kg kg against the loan. |
| `tickets.show` | Show |
| `tickets.sold_at` | Sold at |
| `tickets.spec` | Spec (cylinder weight) |
| `tickets.status` | Status |
| `tickets.supplied_at` | Supplied at |
| `tickets.ticket_details` | Details |
| `tickets.ticket_lift` | Lift |
| `tickets.ticket_not_found` | No ticket found with that barcode. |
| `tickets.ticket_not_restrictable` | Only a published or sold ticket can be restricted. |
| `tickets.ticket_not_restricted` | This ticket is not restricted. |
| `tickets.ticket_publish_date` | Published |
| `tickets.ticket_restrict` | Restrict |
| `tickets.ticket_restricted` | Ticket restricted. |
| `tickets.ticket_sale_deleted` | The sale of this ticket was deleted, so its restriction cannot be lifted. |
| `tickets.ticket_status_publish` | Published |
| `tickets.tickets_table` | Tickets |
| `tickets.total_amount` | Total amount |
| `tickets.usage` | Usage |
| `tickets.view_tickets` | Tickets |

## Ticket summary

Menu key: `menu.ticket_summary`  
Files: `app/Http/Controllers/TicketSummaryController.php`, `resources/views/ticket_summary/*`

| Key | en |
|---|---|
| `menu.ticket_summary` | Summary Tickets |
| `tickets.date` | Date |
| `tickets.export_xls` | Export XLS |
| `tickets.fuel_type` | Fuel type |
| `tickets.kg` | kg |
| `tickets.no_data` | No data for this period. |
| `tickets.show` | Show |
| `tickets.spec` | Spec (cylinder weight) |
| `tickets.summary_balance` | Balance |
| `tickets.summary_count` | Count |
| `tickets.summary_end` | End |
| `tickets.summary_in` | Inbound |
| `tickets.summary_publish` | Publish |
| `tickets.summary_start` | Start |
| `tickets.total` | Total |

