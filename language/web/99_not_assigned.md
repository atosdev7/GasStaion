# Not assigned to a page

Keys that no page uses by a literal string. They are either used through a dynamic key (built at run time) or not used at all (candidates to delete). `crud.*` and `fields.*` hold many keys that belong to one page (tickets, sales, cards, ...).

Each key is shown with its English text (the web app is English only). A key is listed under every page whose code uses it, so shared keys repeat. **MISSING** = absent in that language.

## admin.php (38)

| Key | en |
|---|---|
| `admin.activate` | Activate |
| `admin.active` | Active |
| `admin.backup_table_fuel_inbounds` | Fuel inbounds |
| `admin.backup_table_remainders` | Remainders |
| `admin.backup_table_sales` | Sales |
| `admin.backup_table_supplements` | Supplements |
| `admin.backup_table_tickets` | Supplied / restricted tickets |
| `admin.board_address` | Board RS-485 address |
| `admin.cancel` | Cancel |
| `admin.capacity_kg` | Capacity (kg) |
| `admin.created` | Created. |
| `admin.deactivate` | Deactivate |
| `admin.delete` | Delete |
| `admin.delete_confirm` | Delete this record? It will disappear from lists, but nothing that already refers to it is affected. It can be restored from the trash. |
| `admin.deleted` | Deleted. |
| `admin.density` | Density (kg/L) |
| `admin.device_id` | Device |
| `admin.edit` | Edit |
| `admin.fuel_type` | Fuel type |
| `admin.inactive` | Inactive |
| `admin.ip_address` | IP address |
| `admin.name` | Name |
| `admin.new` | New |
| `admin.number` | Nozzle number |
| `admin.password` | Password |
| `admin.pin` | PIN |
| `admin.price` | Price per kg |
| `admin.purge_days` | Purge days |
| `admin.restore` | Restore |
| `admin.restored` | Restored. |
| `admin.role` | Role |
| `admin.save` | Save |
| `admin.saved` | Saved. |
| `admin.show_active` | Back to list |
| `admin.show_trash` | Show deleted |
| `admin.status` | Status |
| `admin.tank_id` | Tank |
| `admin.username` | Username |

## dashboard.php (3)

| Key | en |
|---|---|
| `dashboard.dash_type_card` | Card sales |
| `dashboard.dash_type_spot` | Spot sales |
| `dashboard.dash_type_ticket` | Ticket sales |

## fuel.php (3)

| Key | en |
|---|---|
| `fuel.mode_auto` | Auto |
| `fuel.mode_hint` | Auto: the system sets the amount and starts the fill. Manual: the supplier types the amount at the dispenser. |
| `fuel.mode_manual` | Manual |

## initialize.php (4)

| Key | en |
|---|---|
| `initialize.ticket_status_publish` | Published |
| `initialize.ticket_status_restrict` | Restricted |
| `initialize.ticket_status_sale` | Sold |
| `initialize.ticket_status_supply` | Supplied |

## menu.php (1)

| Key | en |
|---|---|
| `menu.confirmations` | Daily Confirmation |

## sales.php (22)

| Key | en |
|---|---|
| `sales.activate` | Activate |
| `sales.active` | Active |
| `sales.blocked_confirmed` | It is confirmed and can no longer be deleted. |
| `sales.blocked_used` | The card was used since: its balance would go below zero. |
| `sales.created` | Created. |
| `sales.deactivate` | Deactivate |
| `sales.delete_confirm` | Delete this record? It will disappear from lists, but nothing that already refers to it is affected. It can be restored from the trash. |
| `sales.deleted` | Deleted. |
| `sales.inactive` | Inactive |
| `sales.name` | Name |
| `sales.new` | New |
| `sales.phone` | Phone |
| `sales.restore` | Restore |
| `sales.restored` | Restored. |
| `sales.sale_type_card` | Card |
| `sales.sale_type_spot` | Spot |
| `sales.sale_type_ticket` | Ticket |
| `sales.saved` | Saved. |
| `sales.show_active` | Back to list |
| `sales.show_trash` | Show deleted |
| `sales.status` | Status |

## supplements.php (6)

| Key | en |
|---|---|
| `supplements.mode_auto` | Auto |
| `supplements.mode_manual` | Manual |
| `supplements.ticket_status_publish` | Published |
| `supplements.ticket_status_restrict` | Restricted |
| `supplements.ticket_status_sale` | Sold |
| `supplements.ticket_status_supply` | Supplied |

## tickets.php (12)

| Key | en |
|---|---|
| `tickets.payment_bank` | Bank |
| `tickets.payment_cash` | Cash |
| `tickets.sale_type_card` | Card |
| `tickets.sale_type_spot` | Spot |
| `tickets.sale_type_ticket` | Ticket |
| `tickets.ticket_status_restrict` | Restricted |
| `tickets.ticket_status_sale` | Sold |
| `tickets.ticket_status_supply` | Supplied |
| `tickets.usage_other` | Other (given away) |
| `tickets.usage_sale` | Sale |
| `tickets.usage_self` | Self use |
| `tickets.usage_ticket_loan` | Ticket loan |

