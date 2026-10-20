# Config for admin

Sidebar group: `menu.group_admin` = Config for Admin

Each key is shown with its English text (the web app is English only). A key is listed under every page whose code uses it, so shared keys repeat. **MISSING** = absent in that language.

## Users (generic list/form screens)

Menu key: `menu.users`  
Files: `app/Http/Controllers/UserController.php`

| Key | en |
|---|---|
| `admin.password_hint` | Leave blank to keep the current password. It is also the Smart210 login password. |
| `menu.users` | Users |

## Smart210 devices (generic list/form screens)

Menu key: `menu.devices`  
Files: `app/Http/Controllers/DeviceController.php`

| Key | en |
|---|---|
| `admin.ip_address_hint` | The address this Smart210 calls from. Only registered addresses may use the API, so give the terminal a fixed address (or a reservation on the router). |
| `admin.pin_hint` | Machine Config PIN, 4 to 8 digits. Leave blank to keep it (new devices get the default PIN). |
| `admin.purge_days_hint` | Synced records older than this many days are removed from the Smart210. |
| `menu.devices` | Smart210 Devices |

## Fuel types (generic list/form screens)

Menu key: `menu.fuel`  
Files: `app/Http/Controllers/FuelTypeController.php`

| Key | en |
|---|---|
| `admin.density_hint` | Weight per litre, e.g. for converting between an LPG ticket's kg and a litre-based tank/report. Leave blank if not needed. |
| `menu.fuel` | Fuel Types |

## Tanks (generic list/form screens)

Menu key: `menu.tanks`  
Files: `app/Http/Controllers/TankController.php`

| Key | en |
|---|---|
| `menu.tanks` | Tanks |

## Nozzles (generic list/form screens)

Menu key: `menu.nozzles`  
Files: `app/Http/Controllers/NozzleController.php`, `resources/views/crud/script_nozzles.blade.php`

| Key | en |
|---|---|
| `admin.board_address_hint` | The address of the nozzle's board on the RS-485 line; leave it empty when it is not known yet. |
| `admin.nozzle_fuel_mismatch` | The tank holds a different fuel type than the nozzle. |
| `admin.nozzle_number_taken` | This number is already used on this device (a nozzle that was deleted keeps its number: restore it instead). |
| `menu.nozzles` | Nozzles |

## Sync status

Menu key: `menu.sync`  
Files: `app/Http/Controllers/SyncController.php`, `resources/views/sync/*`

| Key | en |
|---|---|
| `menu.sync` | Sync Status |
| `sync.conflict` | Conflict |
| `sync.device` | Device |
| `sync.devices` | Smart210 devices |
| `sync.errors` | Rows the server rejected, and conflicts |
| `sync.initial_done` | Set up |
| `sync.last_synced` | Last upload |
| `sync.never` | Never |
| `sync.no` | No |
| `sync.no_errors` | Nothing to review. |
| `sync.ok` | OK |
| `sync.open_count` | :count open |
| `sync.payload` | Row |
| `sync.reason` | Reason |
| `sync.record` | Record |
| `sync.resolve` | Resolve |
| `sync.resolve_confirm` | Mark this as dealt with? |
| `sync.resolved` | Marked as resolved. |
| `sync.resolved_by` | Resolved by :name on :date |
| `sync.show_all` | Show resolved too |
| `sync.show_open` | Show open only |
| `sync.stale` | Out of date |
| `sync.stale_hint` | A Smart210 that has not uploaded its data for :hours hours is out of date (the cashier uploads once a day at check time). Check it before restricting a ticket: it could still sell or supply that ticket. |
| `sync.state` | State |
| `sync.table` | Table |
| `sync.times` | Times |
| `sync.when` | Last seen |
| `sync.yes` | Yes |

## Backup

Menu key: `menu.backup`  
Files: `app/Http/Controllers/BackupController.php`, `app/Support/BackupRun.php`, `resources/views/backup/*`

| Key | en |
|---|---|
| `admin.backup_cleared` | Removed |
| `admin.backup_confirm` | Export all data and permanently remove the rows shown? A safety copy of the database is made first. |
| `admin.backup_daily_hint` | A full database copy is also saved every night at 02:00 and kept for :days days. |
| `admin.backup_date` | Run at |
| `admin.backup_done` | Backup finished: :file |
| `admin.backup_file` | Export file |
| `admin.backup_history` | Backup history |
| `admin.backup_kept_hint` | Debts, payments, ticket loans and settlements are never removed, and neither is anything not yet checked out. The latest possible date is yesterday. |
| `admin.backup_preview` | Preview |
| `admin.backup_run` | Export and clear |
| `admin.backup_tank_carry` | Tank stock carried forward (inbound minus supplied in the removed rows, still counted in tank levels) |
| `admin.backup_up_to` | Clear up to |
| `admin.backup_will_clear` | Exports all data, then permanently removes these rows up to :date |
| `admin.kg` | kg |
| `menu.backup` | Backup |
| `supplements.nozzle_id` | Nozzle |
| `supplements.supply_date` | Supply date |

## Audit log

Menu key: `menu.audit`  
Files: `app/Http/Controllers/AuditController.php`, `app/Support/Audit.php`, `resources/views/audit/*`

| Key | en |
|---|---|
| `admin.from` | From |
| `admin.no_data` | No data for this period. |
| `admin.to` | To |
| `audit.action` | Action |
| `audit.detail` | Detail |
| `audit.entries` | :count entries |
| `audit.hint` | What was done in the app and by whom, newest first. A day is one file; the log keeps about 90 days. |
| `audit.show` | Show |
| `audit.user` | User |
| `audit.when` | When |
| `menu.audit` | Audit Log |

## Shared in this menu (controller messages, exports, dynamic keys)

| Key | en |
|---|---|
| `roles.accountant` | Accountant |
| `roles.admin` | Admin |
| `roles.assistant` | Assistant |
| `roles.cashier` | Cashier |
| `roles.manager` | Manager |

