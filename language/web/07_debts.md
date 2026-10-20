# Debts

Sidebar group: `menu.group_debts` = Debts Management

Each key is shown with its English text (the web app is English only). A key is listed under every page whose code uses it, so shared keys repeat. **MISSING** = absent in that language.

## Debt payments

Menu key: `menu.payments`  
Files: `app/Http/Controllers/DebtController.php`, `resources/views/debt/*`

| Key | en |
|---|---|
| `debts.actions` | Actions |
| `debts.all_customers` | All customers |
| `debts.all_payment_methods` | All payment methods |
| `debts.all_statuses` | All states |
| `debts.amount` | Amount |
| `debts.apply_to_debt` | Apply to |
| `debts.apply_to_debt_hint` | Leave as general payment if it's not clear which of the customer's debts this pays down. |
| `debts.balance` | Balance |
| `debts.cancel` | Cancel |
| `debts.clear_filters` | Clear |
| `debts.created` | Created. |
| `debts.customer_id` | Customer |
| `debts.debt_customer_mismatch` | That debt does not belong to the selected customer. |
| `debts.debt_open` | Open |
| `debts.debt_settled` | Settled |
| `debts.debt_view_sale` | View sale |
| `debts.debts` | Debts |
| `debts.general_payment_option` | General payment (not tied to a specific debt) |
| `debts.no_data` | No data for this period. |
| `debts.note` | Note |
| `debts.opening_balance` | Opening balance |
| `debts.paid_date` | Paid date |
| `debts.payment_bank` | Bank |
| `debts.payment_cash` | Cash |
| `debts.payment_method` | Payment method |
| `debts.record_payment` | Record payment |
| `debts.sale_date` | Sale date |
| `debts.settled_amount` | Settled amount |
| `debts.show` | Show |
| `debts.status` | Status |
| `debts.total_balance` | Total balance |
| `debts.total_owed` | Total owed |
| `debts.total_settled` | Total settled |
| `debts.unallocated_payments` | Unallocated payments |
| `debts.unallocated_payments_hint` | Received but not yet matched to a specific debt. |
| `menu.payments` | Payments |

## Ticket loans

Menu key: `menu.ticket_loans`  
Files: `app/Http/Controllers/LoanController.php`, `resources/views/loan/*`

| Key | en |
|---|---|
| `debts.all_customers` | All customers |
| `debts.all_fuel_types` | All fuel types |
| `debts.all_statuses` | All states |
| `debts.amount_kg` | Weight (kg) |
| `debts.apply_to_loan` | Apply to loan |
| `debts.balance` | Balance |
| `debts.cancel` | Cancel |
| `debts.clear_filters` | Clear |
| `debts.customer_id` | Customer |
| `debts.debt_open` | Open |
| `debts.debt_settled` | Settled |
| `debts.fuel_type` | Fuel type |
| `debts.loaned_at` | Loaned at |
| `debts.no_data` | No data for this period. |
| `debts.no_open_loans` | There are no open ticket loans to settle. |
| `debts.record_settlement` | Record settlement |
| `debts.settled_amount` | Settled amount |
| `debts.settled_loan` | Settled :kg kg against the loan. |
| `debts.show` | Show |
| `debts.status` | Status |
| `debts.ticket_barcode` | Ticket barcode |
| `debts.ticket_barcode_hint` | Scan or type the barcode of the ticket the customer is surrendering. |
| `debts.ticket_fuel_type_mismatch` | That ticket's fuel type doesn't match the loan's. |
| `debts.ticket_not_settleable` | That barcode isn't a sold ticket that can be surrendered (already used, never sold, or doesn't exist). |
| `debts.total_balance` | Total balance |
| `debts.total_loaned` | Total loaned |
| `debts.total_settled` | Total settled |
| `menu.ticket_loans` | Ticket Loans |

## Debt summary

Menu key: `menu.debt_summary`  
Files: `app/Http/Controllers/DebtSummaryController.php`, `resources/views/debt_summary/*`

| Key | en |
|---|---|
| `debt_summary.closing` | Closing balance |
| `debt_summary.hint` | Per customer, from the start date to the end date: what was owed at the start, what was added, what was paid and what is owed at the end. |
| `debt_summary.loaned` | Loaned |
| `debt_summary.opening` | Opening balance |
| `debt_summary.settled` | Settled |
| `debts.customer_id` | Customer |
| `debts.export_xls` | Export XLS |
| `debts.from` | From |
| `debts.no_data` | No data for this period. |
| `debts.payment_bank` | Bank |
| `debts.payment_cash` | Cash |
| `debts.show` | Show |
| `debts.to` | To |
| `debts.total` | Total |
| `menu.debt_summary` | Summary |
| `menu.group_debts` | Debts Management |
| `menu.payments` | Payments |
| `menu.ticket_loans` | Ticket Loans |

## Shared in this menu (controller messages, exports, dynamic keys)

| Key | en |
|---|---|
| `debt_summary.debts` | New debts |
| `debt_summary.payments` | Payments received |

