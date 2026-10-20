# Supplements

Sidebar group: `menu.group_supplements` = Supplements

Each key is shown with its English text (the web app is English only). A key is listed under every page whose code uses it, so shared keys repeat. **MISSING** = absent in that language.

## Supplements

Menu key: `menu.supplements`  
Files: `app/Http/Controllers/SupplementController.php`, `resources/views/supplement/*`

| Key | en |
|---|---|
| `menu.supplements` | Supplements |
| `supplements.actions` | Actions |
| `supplements.all_modes` | All modes |
| `supplements.all_nozzles` | All nozzles |
| `supplements.amount_kg` | Weight (kg) |
| `supplements.back_to_list` | Back to the list |
| `supplements.barcode` | Barcode |
| `supplements.cancel` | Cancel |
| `supplements.card` | Card |
| `supplements.clear_filters` | Clear |
| `supplements.comment` | Comment |
| `supplements.deleted_at` | Deleted at |
| `supplements.density` | Density (kg/L) |
| `supplements.density_not_configured` | This fuel type has no density configured yet, so litres can't be converted to kg. |
| `supplements.fuel_type` | Fuel type |
| `supplements.kg` | kg |
| `supplements.liter` | L |
| `supplements.mode` | Mode |
| `supplements.mode_hint` | Auto: the system sets the amount and starts the fill. Manual: the supplier types the amount at the dispenser. |
| `supplements.no_data` | No data for this period. |
| `supplements.nozzle_fuel_type_mismatch` | That ticket's fuel type doesn't match the nozzle's. |
| `supplements.nozzle_id` | Nozzle |
| `supplements.out_amount` | Out amount (kg / L) |
| `supplements.out_amount_lt` | Out amount (L) |
| `supplements.preset_kg` | Tickets total (kg) |
| `supplements.record_supplement` | Record supplement |
| `supplements.remainder` | Remainder |
| `supplements.sale_confirmed` | Confirmed |
| `supplements.sale_tickets_first` | Only the first :count tickets are listed. |
| `supplements.sale_view` | View |
| `supplements.show` | Show |
| `supplements.supplement_barcode_hint` | Scan or type the barcodes of the tickets the customer is using at the pump, one per line. |
| `supplements.supplement_details` | Supply details |
| `supplements.supplement_not_found` | The supply was not found. |
| `supplements.supplied` | Supplied :kg kg. |
| `supplements.supplied_at` | Supplied at |
| `supplements.supplied_more_than_tickets` | That is more than the tickets cover (:preset kg). |
| `supplements.supplied_with_remainder` | Supplied :kg kg. Remainder ticket :barcode for :left kg was created. |
| `supplements.supply_date` | Supply date |
| `supplements.supply_density_hint` | Leave it as filled in (the density of the fuel type) unless this supply was converted with another one. |
| `supplements.supply_tickets` | Tickets used |
| `supplements.ticket_barcodes` | Ticket barcodes |
| `supplements.tickets_not_suppliable` | These barcodes aren't sold tickets that can be supplied (already used, restricted, never sold, or don't exist): :barcodes |
| `supplements.total_dispensed_kg` | Total dispensed (kg) |
| `supplements.total_dispensed_liters` | Total dispensed (L) |

## Remainders

Menu key: `menu.remainders`  
Files: `app/Http/Controllers/RemainderController.php`, `app/Support/RemainderMaker.php`, `resources/views/remainder/*`

| Key | en |
|---|---|
| `menu.remainders` | Remainders |
| `supplements.add_remainder` | Add remainder |
| `supplements.all_fuel_types` | All fuel types |
| `supplements.amount_kg` | Weight (kg) |
| `supplements.barcode` | Barcode |
| `supplements.barcode_contains` | Barcode contains |
| `supplements.cancel` | Cancel |
| `supplements.clear_filters` | Clear |
| `supplements.comment` | Comment |
| `supplements.complete_date` | Complete date |
| `supplements.fuel_type` | Fuel type |
| `supplements.no_data` | No data for this period. |
| `supplements.no_supply_for_remainder` | There is no supply that can still give a remainder: every supply either has one or used all its tickets. |
| `supplements.remainder_added` | Remainder ticket :barcode of :kg kg added. |
| `supplements.remainder_comment_saved` | The comment was saved. |
| `supplements.remainder_date` | Remainder date |
| `supplements.remainder_exists` | This supply already has a remainder. |
| `supplements.remainder_of_supply` | From supply |
| `supplements.remainder_opening` | Opening remainder (no supply) |
| `supplements.remainder_room` | Left: :kg kg |
| `supplements.remainder_supply_cleared` | Supply cleared by a backup |
| `supplements.remainder_supply_hint` | Only a supply that gave less than its tickets covered can have a remainder; the weight is at most what was not dispensed. |
| `supplements.remainder_too_much` | The weight can be at most :kg kg (what the supply did not dispense). |
| `supplements.remainders_total_kg` | Remainder tickets, total (kg) |
| `supplements.save` | Save |
| `supplements.show` | Show |
| `supplements.status` | Status |

