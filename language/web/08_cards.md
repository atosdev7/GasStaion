# Cards

Sidebar group: `menu.group_cards` = Cards Management

Each key is shown with its English text (the web app is English only). A key is listed under every page whose code uses it, so shared keys repeat. **MISSING** = absent in that language.

## Cards (list, edit, PIN, history)

Menu key: `menu.cards`  
Files: `app/Http/Controllers/CardController.php`, `resources/views/card/index.blade.php`, `resources/views/card/edit.blade.php`, `resources/views/card/pin.blade.php`, `resources/views/card/history.blade.php`

| Key | en |
|---|---|
| `cards.actions` | Actions |
| `cards.all_fuel_types` | All fuel types |
| `cards.all_statuses` | All states |
| `cards.already_blocked` | This card is already blocked. |
| `cards.back_to_cards` | Cards |
| `cards.balance` | Balance (kg) |
| `cards.balance_after` | Balance after (kg) |
| `cards.block` | Block (lost card) |
| `cards.block_confirm` | Block this card? It cannot be used or topped up until it is unblocked. |
| `cards.blocked` | The card is blocked. |
| `cards.cancel` | Cancel |
| `cards.clear_filters` | Clear |
| `cards.comment` | Comment |
| `cards.edit` | Edit card |
| `cards.edit_button` | Edit |
| `cards.edit_title` | Edit the card :card |
| `cards.entry_type` | Entry |
| `cards.from` | From |
| `cards.fuel_type` | Fuel type |
| `cards.history` | History |
| `cards.history_hint` | Every top-up and fill of the card in the order they happened, with the balance after each. An entry is never deleted: a deleted top-up or fill stays and is undone by a reversal. |
| `cards.history_of` | History of the card of :name |
| `cards.holder` | Customer name |
| `cards.kg_in_out` | kg in / out |
| `cards.ledger_differs` | The ledger says :ledger kg, the top-up and supply records that still exist add up to :records kg. The ledger is the balance; the difference means a top-up or a supply was removed for good. |
| `cards.new_pin` | New PIN |
| `cards.no_cards` | No cards yet. The cashier registers a card on the Smart210. |
| `cards.no_data` | No data for this period. |
| `cards.not_blocked` | This card is not blocked. |
| `cards.not_locked` | This card is not locked. |
| `cards.phone` | Phone number |
| `cards.pin_hint` | 4 to 8 digits, given by the customer; the cashier types it on the Smart210. A card locked by wrong PINs is unlocked by it. |
| `cards.pin_saved` | The PIN is changed. |
| `cards.pin_title` | New PIN for :card |
| `cards.price` | Price per kg |
| `cards.reset_pin` | Reset PIN |
| `cards.save` | Save |
| `cards.search` | Name or card ID |
| `cards.show` | Show |
| `cards.status` | Status |
| `cards.to` | To |
| `cards.top_up` | Top up |
| `cards.total_amount` | Total amount |
| `cards.tries` | :n wrong PINs |
| `cards.uid` | Card ID |
| `cards.unblock` | Unblock |
| `cards.unblocked` | The card is unblocked. |
| `cards.unlock` | Unlock |
| `cards.unlocked` | The card is unlocked. |
| `cards.updated` | The card is saved. |
| `cards.user` | User |
| `cards.when` | When |
| `menu.cards` | Cards |

## Card top-ups

Menu key: `menu.card_topups`  
Files: `app/Http/Controllers/TopUpController.php`, `resources/views/card/topups.blade.php`, `resources/views/card/topup_create.blade.php`

| Key | en |
|---|---|
| `cards.all_cards` | All cards |
| `cards.amount_kg` | Weight (kg) |
| `cards.balance_now` | Balance now |
| `cards.by` | By |
| `cards.cancel` | Cancel |
| `cards.card` | Card |
| `cards.card_blocked` | A blocked card cannot be topped up. |
| `cards.choose_card` | Choose a card |
| `cards.clear_filters` | Clear |
| `cards.fuel_type` | Fuel type |
| `cards.holder` | Customer name |
| `cards.no_cards` | No cards yet. The cashier registers a card on the Smart210. |
| `cards.no_data` | No data for this period. |
| `cards.payment_cash_only` | A card is paid in cash only: no bank, no debt. The price of the fuel is the default and can be changed. |
| `cards.price` | Price per kg |
| `cards.sale_date` | Sale date |
| `cards.show` | Show |
| `cards.time` | Time |
| `cards.top_up` | Top up |
| `cards.topped_up` | Topped up :kg kg on the card of :name. |
| `cards.topup_title` | Top up a card |
| `cards.topups_total_kg` | Topped up, total (kg) |
| `cards.total_amount` | Total amount |
| `cards.uid` | Card ID |
| `menu.card_topups` | Top Ups |

## Card summary

Menu key: `menu.card_summary`  
Files: `app/Http/Controllers/CardSummaryController.php`, `resources/views/card/summary.blade.php`

| Key | en |
|---|---|
| `cards.closing` | Closing balance (kg) |
| `cards.export_xls` | Export XLS |
| `cards.from` | From |
| `cards.fuel_type` | Fuel type |
| `cards.holder` | Customer name |
| `cards.no_data` | No data for this period. |
| `cards.opening` | Opening balance (kg) |
| `cards.show` | Show |
| `cards.summary_hint` | Per card, from the start date to the end date: what the card held at the start, what was topped up, what was used and what it holds at the end. |
| `cards.to` | To |
| `cards.topped_up_amount` | Topped up (amount) |
| `cards.topped_up_kg` | Topped up (kg) |
| `cards.total` | Total |
| `cards.uid` | Card ID |
| `cards.used_kg` | Used (kg) |
| `menu.card_summary` | Summary |
| `menu.group_cards` | Cards Management |

## Shared in this menu (controller messages, exports, dynamic keys)

| Key | en |
|---|---|
| `cards.entry_fill` | Fill |
| `cards.entry_reversal` | Reversal (deleted) |
| `cards.entry_topup` | Top-up |
| `cards.status_active` | Active |
| `cards.status_blocked` | Blocked (lost) |
| `cards.status_locked` | Locked (wrong PIN) |

