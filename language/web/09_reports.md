# Reports

Sidebar group: `menu.group_reports` = Report

Each key is shown with its English text (the web app is English only). A key is listed under every page whose code uses it, so shared keys repeat. **MISSING** = absent in that language.

## Reports (index)

Menu key: `menu.reports`  
Files: `resources/views/report/index.blade.php`

| Key | en |
|---|---|
| `menu.reports` | Reports |

## Report: sales

Menu key: (sub-page, no menu item)  
Files: `resources/views/report/sales.blade.php`

| Key | en |
|---|---|
| `report.amount_kg` | Weight (kg) |
| `report.by_day` | By day |
| `report.by_type` | By type |
| `report.count` | Count |
| `report.day` | Date |
| `report.fuel_type` | Fuel type |
| `report.no_data` | No data for this period. |
| `report.paid_amount` | Paid amount |
| `report.payment_method` | Payment method |
| `report.sale_type` | Sale type |
| `report.title_sales` | Sales |
| `report.total` | Total |
| `report.total_amount` | Total amount |
| `report.unpaid_amount` | Unpaid amount |
| `report.usage` | Usage |

## Report: cash

Menu key: (sub-page, no menu item)  
Files: `resources/views/report/cash.blade.php`

| Key | en |
|---|---|
| `report.day` | Date |
| `report.from_payments` | Debt payments |
| `report.from_sales` | Sale payments |
| `report.no_data` | No data for this period. |
| `report.payment_method` | Payment method |
| `report.title_cash` | Cash and bank |
| `report.total` | Total |

## Report: profit

Menu key: (sub-page, no menu item)  
Files: `resources/views/report/profit.blade.php`

| Key | en |
|---|---|
| `report.amount_kg` | Weight (kg) |
| `report.cost` | Cost |
| `report.fuel_type` | Fuel type |
| `report.missing_cost` | Sales without cost |
| `report.missing_cost_hint` | Sales entered without a cost count as zero cost, so their profit is overstated. |
| `report.no_data` | No data for this period. |
| `report.profit` | Profit |
| `report.profit_usage_hint` | Self use, given-away and ticket-loan rows have no revenue, so their profit is the cost of the fuel. |
| `report.revenue` | Revenue |
| `report.title_profit` | Profit |
| `report.total` | Total |
| `report.usage` | Usage |

## Report: customer

Menu key: (sub-page, no menu item)  
Files: `resources/views/report/customer.blade.php`

| Key | en |
|---|---|
| `report.amount` | Amount |
| `report.amount_kg` | Weight (kg) |
| `report.balance` | Balance |
| `report.debts` | Debts (all time) |
| `report.fuel_type` | Fuel type |
| `report.loaned_at` | Loaned at |
| `report.loans` | Ticket loans (all time) |
| `report.no_data` | No data for this period. |
| `report.note` | Note |
| `report.open_debt_balance` | Open debt balance |
| `report.open_loan_kg` | Open ticket loans (kg) |
| `report.opening_balance` | Opening balance |
| `report.paid_amount` | Paid amount |
| `report.paid_date` | Paid date |
| `report.payment_method` | Payment method |
| `report.payments_in_period` | Payments in this period |
| `report.sale_date` | Sale date |
| `report.sale_type` | Sale type |
| `report.sales_in_period` | Sales in this period |
| `report.settled_amount` | Settled amount |
| `report.title_customer` | Customer statement |
| `report.total_amount` | Total amount |
| `report.unallocated` | Unallocated payments |
| `report.unpaid_amount` | Unpaid amount |
| `report.usage` | Usage |

## Report: supply

Menu key: (sub-page, no menu item)  
Files: `resources/views/report/supply.blade.php`

| Key | en |
|---|---|
| `report.amount_kg` | Weight (kg) |
| `report.count` | Count |
| `report.day` | Date |
| `report.liters` | Litres |
| `report.no_data` | No data for this period. |
| `report.nozzle_id` | Nozzle |
| `report.title_supply` | Supply |
| `report.total` | Total |

## Report: tanks

Menu key: (sub-page, no menu item)  
Files: `resources/views/report/tanks.blade.php`

| Key | en |
|---|---|
| `report.all_reports` | All reports |
| `report.capacity` | Capacity |
| `report.current` | Current |
| `report.export_xls` | Export XLS |
| `report.fuel_type` | Fuel type |
| `report.inbound` | Inbound |
| `report.no_data` | No data for this period. |
| `report.opening` | Opening |
| `report.percent_full` | Full |
| `report.supplied` | Supplied |
| `report.tank_id` | Tank |
| `report.tanks_hint` | All time, in kg. Supplied fuel is counted against the tank of the nozzle it came from. |
| `report.title_tanks` | Tank stock |

## Report: tickets

Menu key: (sub-page, no menu item)  
Files: `resources/views/report/tickets.blade.php`

| Key | en |
|---|---|
| `report.all_reports` | All reports |
| `report.blank` | Blank |
| `report.direct` | Spot (direct) |
| `report.export_xls` | Export XLS |
| `report.fuel_type` | Fuel type |
| `report.kg` | kg |
| `report.no_data` | No data for this period. |
| `report.received` | Received |
| `report.restricted` | Restricted |
| `report.sold` | Sold |
| `report.spec` | Spec (cylinder weight) |
| `report.supplied` | Supplied |
| `report.title_tickets` | Ticket stock |
| `report.unsold` | Published, unsold |

## Shared in this menu (controller messages, exports, dynamic keys)

| Key | en |
|---|---|
| `menu.card_summary` | Summary |
| `menu.payments` | Payments |
| `menu.remainders` | Remainders |
| `menu.ticket_loans` | Ticket Loans |
| `report.barcode` | Barcode |
| `report.cards_opening` | Opening balance (kg) |
| `report.choose_customer` | Choose a customer |
| `report.closing` | Closing balance (kg) |
| `report.comment` | Comment |
| `report.complete_date` | Complete date |
| `report.confirmed` | Confirmed |
| `report.confirmed_yes` | Yes |
| `report.customer_id` | Customer |
| `report.date` | Date |
| `report.debt_summary_closing` | Closing balance |
| `report.debt_summary_opening` | Opening balance |
| `report.desc_cash` | Money received per day, by payment method: sale payments and debt payments. |
| `report.desc_customer` | One customer's sales, payments, debts and ticket loans. |
| `report.desc_profit` | Revenue minus cost (cost per kg x kg) per fuel type and usage. |
| `report.desc_sales` | Kg and money sold, by fuel type, sale type, usage and payment method. |
| `report.desc_supply` | Supplements per day and nozzle, in litres and kg. |
| `report.desc_tanks` | Current stock per tank: opening + inbound - supplied. |
| `report.desc_tickets` | Tickets per fuel type and spec: received, published, sold, supplied, restricted. |
| `report.from` | From |
| `report.holder` | Customer name |
| `report.loaned` | Loaned |
| `report.low_tank` | :tank is low: :kg kg left. |
| `report.mode` | Mode |
| `report.mode_auto` | Auto |
| `report.mode_hint` | Auto: the system sets the amount and starts the fill. Manual: the supplier types the amount at the dispenser. |
| `report.mode_manual` | Manual |
| `report.out_amount_kg` | Out amount (kg) |
| `report.payment_bank` | Bank |
| `report.payment_cash` | Cash |
| `report.preset_amount` | Preset amount (kg) |
| `report.remainder` | Remainder |
| `report.remainder_of_supply` | From supply |
| `report.sale_type_card` | Card |
| `report.sale_type_spot` | Spot |
| `report.sale_type_ticket` | Ticket |
| `report.sales` | Sales |
| `report.settled` | Settled |
| `report.show` | Show |
| `report.sum_count` | Count |
| `report.sum_kind_card` | Card |
| `report.sum_kind_others` | Others |
| `report.sum_kind_remainder` | Remainder |
| `report.sum_kind_self` | Self |
| `report.sum_kind_spot` | Spot |
| `report.sum_kind_ticket` | Tickets |
| `report.sum_made` | Made |
| `report.sum_ticket_kind` | Ticket |
| `report.sum_used` | Used up |
| `report.summary_balance` | Balance |
| `report.summary_count` | Count |
| `report.summary_end` | End |
| `report.summary_in` | Inbound |
| `report.summary_publish` | Publish |
| `report.summary_start` | Start |
| `report.supplements` | Supplements |
| `report.supplied_at` | Supplied at |
| `report.tickets` | Tickets |
| `report.time` | Time |
| `report.to` | To |
| `report.topped_up_amount` | Topped up (amount) |
| `report.topped_up_kg` | Topped up (kg) |
| `report.uid` | Card ID |
| `report.usage_other` | Other (given away) |
| `report.usage_sale` | Sale |
| `report.usage_self` | Self use |
| `report.usage_ticket_loan` | Ticket loan |
| `report.used_kg` | Used (kg) |

