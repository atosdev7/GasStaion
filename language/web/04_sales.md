# Sales

Sidebar group: `menu.group_sales` = Sales Management

Each key is shown with its English text (the web app is English only). A key is listed under every page whose code uses it, so shared keys repeat. **MISSING** = absent in that language.

## Ticket sales

Menu key: `menu.ticket_sales`  
Files: `resources/views/sale/index.blade.php`, `resources/views/sale/create.blade.php`, `resources/views/sale/show.blade.php`, `resources/views/sale/_detail.blade.php`, `resources/views/sale/_customer.blade.php`, `resources/views/sale/edit.blade.php`, `resources/views/sale/return.blade.php`

| Key | en |
|---|---|
| `menu.spot_sales` | Spot Sales |
| `menu.ticket_sales` | Ticket Sales |
| `sale_delete.confirm` | Delete this sale? Its tickets go back as they were and its debt or loan is cancelled. This is for a sale entered by mistake. |
| `sale_delete.delete` | Delete |
| `sale_delete.deleted_at` | Deleted at |
| `sale_delete.deleted_note` | Kept for review. A deleted sale is erased for good when the accountant confirms its date. |
| `sale_delete.deleted_title` | Deleted sales |
| `sale_delete.show_deleted` | Deleted sales |
| `sale_delete.show_recent` | Recent sales |
| `sale_edit.hint` | Only the date, the comment and the receipt can be changed. The weight, the price, the payment and the tickets stay as they were entered; a sale entered wrong is deleted and entered again. |
| `sale_edit.locked` | Confirmed: take the confirmation back to edit it |
| `sale_edit.title` | Edit the sale |
| `sale_return.all` | All unused tickets of this sale |
| `sale_return.badge` | Return |
| `sale_return.barcodes_hint` | Scan or type the barcodes of the tickets the customer hands back, one per line. |
| `sale_return.button` | Return |
| `sale_return.comment` | Reason (optional) |
| `sale_return.money_hint` | The value returned first cancels what the customer still owes on this sale; the rest is refunded in the sale's payment method. The tickets go back to stock, and this is recorded as a new entry dated today. |
| `sale_return.none_unused` | None: every ticket of this sale was used or restricted. |
| `sale_return.returned_kg` | returned :kg kg |
| `sale_return.returned_so_far` | :kg kg already returned |
| `sale_return.sale` | Sale being corrected |
| `sale_return.still_owed` | Still owed on this sale |
| `sale_return.submit` | Return tickets |
| `sale_return.title` | Return tickets |
| `sale_return.unused` | Tickets that can still be returned |
| `sale_return.whole_only_hint` | This sale can only be returned whole: every one of its tickets must come back. |
| `sales.actions` | Actions |
| `sales.add_item` | Add item |
| `sales.all_customers` | All customers |
| `sales.amount_kg` | Weight (kg) |
| `sales.back_to_list` | Back to the list |
| `sales.cancel` | Cancel |
| `sales.check_item` | Check |
| `sales.clear_filters` | Clear |
| `sales.comment` | Comment |
| `sales.cost` | Cost per kg |
| `sales.customer_add_new` | Add ":name" as a new customer |
| `sales.customer_id` | Customer |
| `sales.customer_new_note` | A new customer will be added when the sale is saved. |
| `sales.customer_search` | Type a customer name |
| `sales.delete` | Delete |
| `sales.edit` | Edit |
| `sales.end_serial` | End serial |
| `sales.fifo_placeholder` | lowest available |
| `sales.fuel_type` | Fuel type |
| `sales.item_not_valid` | Not valid: some tickets from :start to :end are not available to sell. |
| `sales.item_valid` | Valid: serials :start to :end can be sold. |
| `sales.kg` | kg |
| `sales.no_data` | No data for this period. |
| `sales.no_sale_stock` | There are no published tickets to sell yet. Publish tickets first. |
| `sales.not_enough_sale_stock` | There are not enough published tickets of this spec to sell. |
| `sales.paid_amount` | Paid amount |
| `sales.payment_bank` | Bank |
| `sales.payment_cash` | Cash |
| `sales.payment_method` | Payment method |
| `sales.price` | Price per kg |
| `sales.quantity` | Count |
| `sales.receipt` | Receipt |
| `sales.receipt_issued` | Receipt issued |
| `sales.recent_sales` | Recent sales |
| `sales.sale_confirmed` | Confirmed |
| `sales.sale_date` | Sale date |
| `sales.sale_details` | Sale details |
| `sales.sale_items` | Items |
| `sales.sale_items_empty` | No items yet. Add an item on the left. |
| `sales.sale_made` | Made |
| `sales.sale_not_found` | The sale was not found. |
| `sales.sale_type` | Sale type |
| `sales.sale_view` | View |
| `sales.save` | Save |
| `sales.show` | Show |
| `sales.spec` | Spec (cylinder weight) |
| `sales.spot_sale` | Spot sale |
| `sales.start_serial` | Start serial |
| `sales.ticket_barcodes` | Ticket barcodes |
| `sales.ticket_lines` | Tickets to sell |
| `sales.ticket_sale` | Ticket sale |
| `sales.tickets` | Tickets |
| `sales.total_amount` | Total amount |
| `sales.unpaid_amount` | Unpaid amount |
| `sales.usage` | Usage |
| `sales.usage_other` | Other (given away) |
| `sales.usage_sale` | Sale |
| `sales.usage_self` | Self use |
| `sales.usage_ticket_loan` | Ticket loan |

## Spot sales

Menu key: `menu.spot_sales`  
Files: `resources/views/sale/index.blade.php`, `resources/views/sale/create_spot.blade.php`, `resources/views/sale/show.blade.php`, `resources/views/sale/_detail.blade.php`, `resources/views/sale/_customer.blade.php`, `resources/views/sale/edit.blade.php`

| Key | en |
|---|---|
| `menu.spot_sales` | Spot Sales |
| `menu.ticket_sales` | Ticket Sales |
| `sale_delete.confirm` | Delete this sale? Its tickets go back as they were and its debt or loan is cancelled. This is for a sale entered by mistake. |
| `sale_delete.delete` | Delete |
| `sale_delete.deleted_at` | Deleted at |
| `sale_delete.deleted_note` | Kept for review. A deleted sale is erased for good when the accountant confirms its date. |
| `sale_delete.deleted_title` | Deleted sales |
| `sale_delete.show_deleted` | Deleted sales |
| `sale_delete.show_recent` | Recent sales |
| `sale_edit.hint` | Only the date, the comment and the receipt can be changed. The weight, the price, the payment and the tickets stay as they were entered; a sale entered wrong is deleted and entered again. |
| `sale_edit.locked` | Confirmed: take the confirmation back to edit it |
| `sale_edit.title` | Edit the sale |
| `sale_return.badge` | Return |
| `sale_return.button` | Return |
| `sale_return.returned_kg` | returned :kg kg |
| `sales.actions` | Actions |
| `sales.all_customers` | All customers |
| `sales.amount_kg` | Weight (kg) |
| `sales.back_to_list` | Back to the list |
| `sales.cancel` | Cancel |
| `sales.clear_filters` | Clear |
| `sales.comment` | Comment |
| `sales.cost` | Cost per kg |
| `sales.customer_add_new` | Add ":name" as a new customer |
| `sales.customer_id` | Customer |
| `sales.customer_new_note` | A new customer will be added when the sale is saved. |
| `sales.customer_search` | Type a customer name |
| `sales.delete` | Delete |
| `sales.edit` | Edit |
| `sales.end_serial` | End serial |
| `sales.fuel_type` | Fuel type |
| `sales.kg` | kg |
| `sales.no_data` | No data for this period. |
| `sales.paid_amount` | Paid amount |
| `sales.payment_cash` | Cash |
| `sales.payment_method` | Payment method |
| `sales.price` | Price per kg |
| `sales.quantity` | Count |
| `sales.receipt` | Receipt |
| `sales.receipt_issued` | Receipt issued |
| `sales.recent_sales` | Recent sales |
| `sales.sale_confirmed` | Confirmed |
| `sales.sale_date` | Sale date |
| `sales.sale_details` | Sale details |
| `sales.sale_items` | Items |
| `sales.sale_made` | Made |
| `sales.sale_not_found` | The sale was not found. |
| `sales.sale_type` | Sale type |
| `sales.sale_view` | View |
| `sales.save` | Save |
| `sales.show` | Show |
| `sales.spec` | Spec (cylinder weight) |
| `sales.spot_sale` | Spot sale |
| `sales.spot_sale_cash_hint` | Spot sales are always paid in cash. |
| `sales.total_too_big` | The total :total is more than one sale can be (:max). Check the price and the weight. |
| `sales.all_fuels` | All fuel types |
| `sales.all_usages` | All usages |
| `sales.sale_date_too_old` | A sale cannot be dated more than :days days back. Ask the manager. |
| `sales.paid_more_than_total` | The paid amount :paid is more than the total :total: only :total was recorded. Check the value you typed. |
| `sales.paid_more_warning` | The paid amount is more than the total: it could be a wrong value (only the total is recorded). |
| `sales.start_serial` | Start serial |
| `sales.ticket_sale` | Ticket sale |
| `sales.total_amount` | Total amount |
| `sales.unpaid_amount` | Unpaid amount |
| `sales.usage` | Usage |
| `sales.usage_other` | Other (given away) |
| `sales.usage_sale` | Sale |
| `sales.usage_self` | Self use |
| `sales.usage_ticket_loan` | Ticket loan |

## Card sales

Menu key: `menu.card_sales`  
Files: `app/Http/Controllers/CardSaleController.php`, `app/Support/CardSales.php`, `resources/views/card/sales.blade.php`

| Key | en |
|---|---|
| `menu.card_sales` | Card Sales |
| `sale_edit.locked` | Confirmed: take the confirmation back to edit it |
| `sales.actions` | Actions |
| `sales.all_cards` | All cards |
| `sales.amount_kg` | Weight (kg) |
| `sales.by` | By |
| `sales.cards_delete_confirm` | Delete this top-up? |
| `sales.cards_deleted` | The top-up is deleted. |
| `sales.clear_filters` | Clear |
| `sales.delete` | Delete |
| `sales.edit` | Edit |
| `sales.fuel_type` | Fuel type |
| `sales.holder` | Customer name |
| `sales.no_data` | No data for this period. |
| `sales.price` | Price per kg |
| `sales.sale_date` | Sale date |
| `sales.sale_view` | View |
| `sales.show` | Show |
| `sales.total_amount` | Total amount |
| `sales.uid` | Card ID |

## Customers (generic list/form screens)

Menu key: `menu.customers`  
Files: `app/Http/Controllers/CustomerController.php`

| Key | en |
|---|---|
| `menu.customers` | Customers |

## Shared in this menu (controller messages, exports, dynamic keys)

| Key | en |
|---|---|
| `sale_delete.blocked_card` | A top-up is deleted on the Card Sales page, by the manager only. |
| `sale_delete.blocked_confirmed` | This sale was confirmed, so it can no longer be deleted. Use a return entry instead. |
| `sale_delete.blocked_is_return` | A return entry cannot be deleted. |
| `sale_delete.blocked_paid` | A payment was recorded against this sale's debt, so the sale cannot be deleted. |
| `sale_delete.blocked_returned` | Tickets of this sale were already returned, so the sale cannot be deleted. |
| `sale_delete.blocked_settled` | Tickets were already surrendered for this sale's loan, so the sale cannot be deleted. |
| `sale_delete.blocked_ticket_used` | A ticket of this sale was already used or restricted, so the sale cannot be deleted. |
| `sale_delete.deleted` | The sale was deleted and everything it did was undone. |
| `sale_delete.ticket_sale_deleted` | The sale of this ticket was deleted, so its restriction cannot be lifted. |
| `sale_edit.confirmed` | This sale is confirmed, so it cannot be edited. A manager can take the confirmation back first. |
| `sale_edit.moved` | It is now on :date, so it shows on the daily confirmation of that day. |
| `sale_edit.saved` | The sale is saved. |
| `sale_return.done` | Returned :count tickets (:kg kg). :debt of what the customer owed was cancelled and :refund refunded (:method). |
| `sale_return.is_return` | A return entry cannot be returned again. |
| `sale_return.loan_settled` | Tickets were already surrendered for this sale's loan, so it cannot be returned. |
| `sale_return.more_than_remaining` | That is more than is left of this sale. |
| `sale_return.nothing_entered` | Enter the barcodes of the returned tickets, or tick "all unused tickets". |
| `sale_return.nothing_to_return` | This sale has no unused ticket left to return. |
| `sale_return.tickets_not_returnable` | These barcodes are not unused tickets of this sale: :barcodes |
| `sale_return.whole_only` | This sale can only be returned whole: all of its tickets must be unused and handed back together. |
| `sales.customer_cannot_add` | You are not allowed to add customers. |
| `sales.sold` | Sold :count tickets, total :total. |
| `sales.sold_spot` | Sold :kg kg, total :total. |

