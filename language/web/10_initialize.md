# Initialize (opening balances)

Sidebar group: `menu.group_init` = Initialize

Each key is shown with its English text (the web app is English only). A key is listed under every page whose code uses it, so shared keys repeat. **MISSING** = absent in that language.

## Opening tank balances

Menu key: `menu.tanks`  
Files: `resources/views/balance/tanks.blade.php`

| Key | en |
|---|---|
| `initialize.amount_kg` | Weight (kg) |
| `initialize.fuel_type` | Fuel type |
| `initialize.opening_tank_hint` | Leave a tank blank if it has no opening stock. |
| `initialize.opening_tank_stock` | Opening tank stock |
| `initialize.save` | Save |
| `initialize.tank_id` | Tank |
| `menu.group_init` | Initialize |
| `menu.tanks` | Tanks |

## Opening debts

Menu key: `menu.init_debts`  
Files: `resources/views/balance/debts.blade.php`

| Key | en |
|---|---|
| `initialize.amount` | Amount |
| `initialize.customer_id` | Customer |
| `initialize.delete` | Delete |
| `initialize.new` | New |
| `initialize.opening_customer_hint` | Opening debts and ticket loans appear on the Payments and Ticket Loans pages, and are paid or settled there like any other. |
| `initialize.opening_debts` | Opening customer debts |
| `initialize.opening_delete_confirm` | Delete this opening balance? |
| `initialize.payment_bank` | Bank |
| `initialize.payment_cash` | Cash |
| `initialize.payment_method` | Payment method |
| `menu.group_init` | Initialize |
| `menu.init_debts` | Debts |

## Opening ticket loans

Menu key: `menu.ticket_loans`  
Files: `resources/views/balance/loans.blade.php`

| Key | en |
|---|---|
| `initialize.amount_kg` | Weight (kg) |
| `initialize.customer_id` | Customer |
| `initialize.delete` | Delete |
| `initialize.fuel_type` | Fuel type |
| `initialize.new` | New |
| `initialize.opening_customer_hint` | Opening debts and ticket loans appear on the Payments and Ticket Loans pages, and are paid or settled there like any other. |
| `initialize.opening_delete_confirm` | Delete this opening balance? |
| `initialize.opening_loans` | Opening ticket loans |
| `menu.group_init` | Initialize |
| `menu.ticket_loans` | Ticket Loans |

## Opening remainder tickets

Menu key: `menu.init_remainders`  
Files: `resources/views/balance/remainders.blade.php`

| Key | en |
|---|---|
| `initialize.amount_kg` | Weight (kg) |
| `initialize.barcode` | Barcode |
| `initialize.comment` | Comment |
| `initialize.fuel_type` | Fuel type |
| `initialize.new` | New |
| `initialize.no_data` | No data for this period. |
| `initialize.opening_remainder_hint` | Remainder tickets that customers already hold when the program starts. Each one makes a new ticket (already sold) with no supply behind it. A backup never removes a remainder. A wrong entry is stopped by restricting its ticket (Ticket Management). |
| `initialize.opening_remainders` | Opening remainder tickets |
| `initialize.remainder_date` | Remainder date |
| `initialize.status` | Status |
| `menu.group_init` | Initialize |
| `menu.init_remainders` | Remainder tickets |

## Shared in this menu (controller messages, exports, dynamic keys)

| Key | en |
|---|---|
| `initialize.created` | Created. |
| `initialize.deleted` | Deleted. |
| `initialize.opening_debt_has_payments` | This opening debt already has payments, so it can no longer be deleted. |
| `initialize.opening_loan_has_settlements` | This opening loan already has settlements, so it can no longer be deleted. |
| `initialize.opening_remainder_added` | Remainder ticket :barcode of :kg kg added. |
| `initialize.saved` | Saved. |

