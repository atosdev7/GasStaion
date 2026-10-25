<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelType;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Admin\User;
use App\Support\Admin\Audit;
use App\Support\Tickets\Barcode;
use App\Support\Sales\SaleAccess;
use App\Support\Sales\SaleDeletion;
use App\Support\Tickets\SerialCounter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Two ways to sell: a **ticket** sale consumes one or more lines of already-published tickets
// (fixed fuel type for the whole sale, one line per spec — e.g. 5kg x100, 10kg x100 — each either
// FIFO by serial or an explicit start serial); a **spot** sale is an arbitrary weight with no
// pre-existing ticket, so it mints one reserved-code 'direct' ticket on the fly (see
// config/ticket_reserved_codes.php) and is always cash. `usage` = sale/self/other/ticket_loan (see
// App\Models\Sales\Sale) applies the same way to both — only "sale" carries a real amount.
class SaleController extends Controller
{
    const USAGES = ['sale', 'self', 'other', 'ticket_loan'];
    const PAYMENT_METHODS = ['cash', 'bank'];
    const SALE_TYPES = ['ticket', 'spot', 'card'];
    const MAX_TOTAL = 99999999; // the most one sale may be worth (the terminal and its upload have the same limit; the column holds far more)

    // The list page of each way to sell (Ticket Sales, Spot Sales and Card Sales in the menu), also where a finished sale goes back to.
    public static function listRoute($saleType)
    {
        $routes = ['spot' => 'spot-sales.index', 'card' => 'card-sales.index'];

        return isset($routes[$saleType]) ? $routes[$saleType] : 'ticket-sales.index';
    }

    public function index(Request $request)
    {
        return $this->listing($request, 'ticket');
    }

    public function indexSpot(Request $request)
    {
        return $this->listing($request, 'spot');
    }

    private function listing(Request $request, $saleType)
    {
        $deleted = $request->boolean('deleted');
        $filters = $request->validate([
            'sale_date' => 'nullable|date_format:Y-m-d',
            'customer' => 'nullable|integer',
            'fuel_type' => ['nullable', Rule::in(array_keys(config('fuel_types')))],
            'usage' => ['nullable', Rule::in(self::USAGES)],
        ]);

        $query = $deleted
            ? Sale::onlyTrashed()->where('sale_type', $saleType)->orderByDesc('deleted_at')
            : Sale::where('sale_type', $saleType)->orderByDesc('sale_date')->orderByDesc('created_at');
        SaleAccess::scope($query, $request->user(), $saleType); // the cashier sees the sales he issued
        if (!empty($filters['sale_date'])) {
            $query->where('sale_date', $filters['sale_date']);
        }
        if (!empty($filters['customer'])) {
            $query->where('customer_id', $filters['customer']);
        }
        if (!empty($filters['fuel_type'])) {
            $query->where('fuel_type', $filters['fuel_type']);
        }
        if (!empty($filters['usage'])) {
            $query->where('usage', $filters['usage']);
        }
        $sales = $query->paginate(50)->appends($request->query());

        // Why a sale cannot be deleted (null = it can), only worked out for people who may delete.
        $blockers = collect();
        if (!$deleted && $request->user()->can('sales.delete')) {
            $blockers = $sales->mapWithKeys(function ($sale) {
                return [$sale->id => SaleDeletion::blocker($sale)];
            });
        }

        // Sales that still have a sold, unused ticket (a Return button) and how much of each was already returned.
        $returnable = collect();
        $returnedKg = collect();
        if (!$deleted) {
            $ids = $sales->pluck('id');
            if ($request->user()->can('sales.return')) {
                $returnable = Ticket::whereIn('sale_id', $ids)->where('status', config('ticket_statuses.sale'))
                    ->distinct()->pluck('sale_id')->flip();
            }
            $returnedKg = Sale::whereIn('reverses_sale_id', $ids)->selectRaw('reverses_sale_id, -SUM(amount_kg) AS kg')
                ->groupBy('reverses_sale_id')->pluck('kg', 'reverses_sale_id');
        }

        return view('sale.index', [
            'saleType' => $saleType,
            'sales' => $sales,
            'deleted' => $deleted,
            'blockers' => $blockers,
            'returnable' => $returnable,
            'returnedKg' => $returnedKg,
            'fuels' => config('fuel_types'),
            'filters' => $filters,
            'customers' => Customer::withTrashed()->pluck('name', 'id'),
        ]);
    }

    // One sale with the tickets it sold: a page, or only the detail (for the popup of the list) when asked with modal=1.
    public function show(Request $request, $id)
    {
        $sale = Sale::withTrashed()->findOrFail($id);
        abort_unless(SaleAccess::canSee($request->user(), $sale), 403);
        $user = User::find($sale->user_id);
        $customer = Customer::withTrashed()->find($sale->customer_id);
        $card = $sale->card_id ? \App\Models\Cards\Card::withTrashed()->find($sale->card_id) : null;

        $data = [
            'sale' => $sale,
            'customer' => $card ? $card->label : ($customer ? $customer->name : ''),
            'user' => $user ? $user->name : '',
            'fuels' => config('fuel_types'),
            'items' => SaleItem::where('sale_id', $sale->id)->orderBy('id')->get(),
        ];

        return view($request->query('modal') ? 'sale._detail' : 'sale.show', $data);
    }

    // For a sale entered wrong: undoes it (see SaleDeletion), as long as it has not moved on.
    public function destroy($id)
    {
        SaleAccess::authorizeChange(auth()->user(), Sale::findOrFail($id));
        $sale = SaleDeletion::delete($id);

        Audit::log('sale_delete', '#' . $sale->id . ' ' . $sale->sale_type . '/' . $sale->usage . ' ' . $sale->amount_kg . 'kg, total ' . $sale->total_amount);

        return redirect()->route(self::listRoute($sale->sale_type))->with('status', __('sale_delete.deleted'));
    }

    public function create()
    {
        $stock = $this->availableStock()->filter(function ($r) {
            return $r->available > 0;
        });

        return view('sale.create', [
            'stock' => $stock,
            'fuels' => config('fuel_types'),
            'specs' => config('ticket_specs'),
            'serials' => $this->availableSerials(),
            'barcodePrefixes' => $this->barcodePrefixes(),
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
            'prices' => FuelType::pluck('price', 'fuel_type'),
            'costs' => $this->defaultCosts(),
        ]);
    }

    public function createSpot()
    {
        return view('sale.create_spot', [
            'fuels' => config('fuel_types'),
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
            'defaultCustomer' => Customer::individual()->id, // a buyer who pays cash and needs no name
            'prices' => FuelType::pluck('price', 'fuel_type'),
            'costs' => $this->defaultCosts(),
        ]);
    }

    public function store(Request $request)
    {
        $saleType = $request->input('sale_type');
        abort_if($saleType === 'spot' && !$request->user()->can('spot_sales.manage'), 403);
        abort_unless(in_array($saleType, self::SALE_TYPES, true), 422);

        return $saleType === 'spot' ? $this->storeSpot($request) : $this->storeTicket($request);
    }

    private function storeTicket(Request $request)
    {
        $data = $request->validate([
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'lines' => 'required|array|min:1',
            'lines.*.spec' => ['required', Rule::in(array_keys(config('ticket_specs')))],
            'lines.*.quantity' => 'required|integer|min:1|max:100000',
            'lines.*.start_serial' => 'nullable|integer|min:1|max:' . Barcode::MAX_SERIAL,
            // a sale and a ticket loan name their customer; own use and other use are always "Individual"
            'customer_id' => [Rule::requiredIf(function () use ($request) {
                return in_array($request->input('usage'), ['sale', 'ticket_loan'], true) && !$request->filled('new_customer');
            }), 'nullable', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'new_customer' => 'nullable|string|max:100', // a name typed in the form that is not a customer yet
            'price' => ['required', 'numeric', 'min:0', 'max:9999999', self::priceAboveZeroForASale()],
            'cost' => 'nullable|numeric|min:0|max:9999999',
            'usage' => ['required', Rule::in(self::USAGES)],
            'payment_method' => ['required_if:usage,sale', 'nullable', Rule::in(self::PAYMENT_METHODS)],
            'paid_amount' => 'required_if:usage,sale|nullable|numeric|min:0|max:99999999',
            'receipt' => 'nullable|boolean',
            'sale_date' => SaleAccess::dateRules($request->user(), ['nullable']), // the day of the sale; today when it is not sent, and not further back than backdate_days
            'comment' => 'nullable|string|max:255',
        ], SaleAccess::dateMessages());

        $this->checkNewCustomer($data);
        $fuelType = $data['fuel_type'];
        $isSale = $data['usage'] === 'sale';

        $soldAt = $this->soldAt($data);

        $sale = DB::transaction(function () use ($data, $fuelType, $isSale, $soldAt) {
            $data['customer_id'] = $this->customerFor($data);
            $amountKg = 0;
            $ticketCount = 0;
            $matchedBarcodes = [];
            $items = [];

            foreach ($data['lines'] as $line) {
                $spec = (int) $line['spec'];
                $quantity = (int) $line['quantity'];

                $query = Ticket::where('fuel_type', $fuelType)->where('spec', $spec)
                    ->where('status', config('ticket_statuses.publish'));
                if ($matchedBarcodes) {
                    $query->whereNotIn('barcode', $matchedBarcodes); // what an earlier item of this sale already took
                }

                if (!empty($line['start_serial'])) {
                    $start = (int) $line['start_serial'];
                    $end = $start + $quantity - 1;
                    $tickets = $query->whereBetween('serial', [$start, $end])->orderBy('serial')->lockForUpdate()->get();
                } else {
                    // FIFO: oldest (lowest serial) published tickets go first.
                    $tickets = $query->orderBy('serial')->lockForUpdate()->limit($quantity)->get();
                }

                if ($tickets->count() < $quantity) {
                    throw ValidationException::withMessages(['lines' => __('sales.not_enough_sale_stock')]);
                }

                $amountKg += $quantity * config('ticket_specs.' . $spec);
                $ticketCount += $quantity;
                $matchedBarcodes = array_merge($matchedBarcodes, $tickets->pluck('barcode')->all());
                $items[] = ['spec' => $spec, 'start_serial' => (int) $tickets->min('serial'), 'end_serial' => (int) $tickets->max('serial'), 'quantity' => $quantity,
                    'amount_kg' => $quantity * config('ticket_specs.' . $spec)];
            }

            $totalAmount = $isSale ? round($amountKg * $data['price'], 2) : 0;
            self::checkTotal($totalAmount);
            $paidAmount = $isSale ? min(round((float) $data['paid_amount'], 2), $totalAmount) : 0;
            $unpaidAmount = $isSale ? round($totalAmount - $paidAmount, 2) : 0;

            $sale = new Sale;
            $sale->forceFill([
                'customer_id' => $data['customer_id'],
                'sale_type' => 'ticket',
                'fuel_type' => $fuelType,
                'cost' => $this->costOf($data, $fuelType, $soldAt),
                'price' => $data['price'],
                'amount_kg' => $amountKg,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'unpaid_amount' => $unpaidAmount,
                'receipt' => !empty($data['receipt']),
                'usage' => $data['usage'],
                'comment' => $data['comment'] ?? null,
                'sale_date' => $soldAt->toDateString(),
                'synced' => false,
                'user_id' => auth()->id(),
            ])->save();

            // the items as entered, kept with the sale
            foreach ($items as $item) {
                $row = new SaleItem;
                $row->forceFill($item + ['sale_id' => $sale->id])->save();
            }

            Ticket::whereIn('barcode', $matchedBarcodes)->update([
                'status' => config('ticket_statuses.sale'),
                'sale_id' => $sale->id,
                'sale_date' => $soldAt,
                'synced' => false, // a bulk update skips the model's reset; the Smart210 must learn these are sold
            ]);

            $this->afterSaleCreated($sale, $data, $fuelType, $amountKg, $unpaidAmount, $soldAt);

            $sale->setAttribute('ticket_count', $ticketCount);

            return $sale;
        });

        Audit::log('ticket_sale', 'sale #' . $sale->id . ' ' . $data['usage'] . ' ' . $fuelType
            . ' ' . $sale->amount_kg . 'kg, total ' . $sale->total_amount);

        return redirect()->route('ticket-sales.index')->with('status', __('sales.sold', [
            'count' => $sale->getAttribute('ticket_count'),
            'total' => $sale->total_amount,
        ]))->with('warning', $this->overpaidWarning($data, $sale));
    }

    private function storeSpot(Request $request)
    {
        $data = $request->validate([
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'amount_kg' => 'required|numeric|min:0.01|max:999999',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->whereNull('deleted_at')], // nobody named: "Individual"
            'new_customer' => 'nullable|string|max:100', // a name typed in the form that is not a customer yet
            'price' => ['required', 'numeric', 'min:0', 'max:9999999', self::priceAboveZeroForASale()],
            'cost' => 'nullable|numeric|min:0|max:9999999',
            'usage' => ['required', Rule::in(self::USAGES)],
            'paid_amount' => 'required_if:usage,sale|nullable|numeric|min:0|max:99999999',
            'receipt' => 'nullable|boolean',
            'sale_date' => SaleAccess::dateRules($request->user(), ['nullable']), // the day of the sale; today when it is not sent, and not further back than backdate_days
            'comment' => 'nullable|string|max:255',
        ], SaleAccess::dateMessages());

        $this->checkNewCustomer($data);
        $fuelType = $data['fuel_type'];
        $isSale = $data['usage'] === 'sale';
        $amountKg = round((float) $data['amount_kg'], 2);
        $totalAmount = $isSale ? round($amountKg * $data['price'], 2) : 0;
        self::checkTotal($totalAmount);
        $paidAmount = $isSale ? min(round((float) ($data['paid_amount'] ?? 0), 2), $totalAmount) : 0;
        $unpaidAmount = $isSale ? round($totalAmount - $paidAmount, 2) : 0;

        $soldAt = $this->soldAt($data);

        $sale = DB::transaction(function () use ($data, $fuelType, $amountKg, $totalAmount, $paidAmount, $unpaidAmount, $soldAt) {
            $data['customer_id'] = $this->customerFor($data);
            $sale = new Sale;
            $sale->forceFill([
                'customer_id' => $data['customer_id'],
                'sale_type' => 'spot',
                'fuel_type' => $fuelType,
                'cost' => $this->costOf($data, $fuelType, $soldAt),
                'price' => $data['price'],
                'amount_kg' => $amountKg,
                'payment_method' => 'cash', // spot sales are always cash, never a contract/bank sale
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'unpaid_amount' => $unpaidAmount,
                'receipt' => !empty($data['receipt']),
                'usage' => $data['usage'],
                'comment' => $data['comment'] ?? null,
                'sale_date' => $soldAt->toDateString(),
                'synced' => false,
                'user_id' => auth()->id(),
            ])->save();

            $code = config('ticket_reserved_codes.direct');
            $serial = SerialCounter::next($code);
            $now = now();

            Ticket::insert([
                'barcode' => Barcode::makeReserved($code, $serial),
                'fuel_type' => $fuelType,
                'spec' => null,
                'code' => $code,
                'serial' => $serial,
                'amount_kg' => $amountKg,
                'sale_id' => $sale->id,
                'sale_date' => $soldAt,
                'status' => config('ticket_statuses.sale'),
                'synced' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->afterSaleCreated($sale, $data, $fuelType, $amountKg, $unpaidAmount, $soldAt);

            return $sale;
        });

        Audit::log('spot_sale', 'sale #' . $sale->id . ' ' . $data['usage'] . ' ' . $fuelType
            . ' ' . $amountKg . 'kg, total ' . $totalAmount);

        return redirect()->route('spot-sales.index')->with('status', __('sales.sold_spot', [
            'kg' => $amountKg,
            'total' => $totalAmount,
        ]))->with('warning', $this->overpaidWarning($data, $sale));
    }

    // The sale keeps at most its total as paid, so more typed than that is probably a wrong value: said after the sale is saved.
    private function overpaidWarning(array $data, $sale)
    {
        $typed = round((float) ($data['paid_amount'] ?? 0), 2);

        return $data['usage'] === 'sale' && $typed > (float) $sale->total_amount + 0.004
            ? __('sales.paid_more_than_total', ['paid' => $typed, 'total' => $sale->total_amount]) : null;
    }

    // A customer typed into the sale form: only for people who may add customers.
    private function checkNewCustomer(array $data)
    {
        if (!in_array($data['usage'], ['self', 'other'], true) && empty($data['customer_id']) && trim((string) ($data['new_customer'] ?? '')) !== '' && !auth()->user()->can('customers.manage')) {
            throw ValidationException::withMessages(['new_customer' => __('sales.customer_cannot_add')]);
        }
    }

    // The customer of the sale: "Individual" for own use and other use; else the one chosen, else the customer with the typed name (a name that is
    // there already, in any case, is not added twice), else a new one; nobody named (a spot sale) is "Individual" too.
    private function customerFor(array $data)
    {
        if (in_array($data['usage'], ['self', 'other'], true)) {
            return Customer::individual()->id;
        }
        if (!empty($data['customer_id'])) {
            return $data['customer_id'];
        }

        $name = trim((string) ($data['new_customer'] ?? ''));
        if ($name === '') {
            return Customer::individual()->id;
        }
        $existing = Customer::whereRaw('LOWER(name) = ?', [mb_strtolower($name, 'UTF-8')])->first();
        if ($existing) {
            return $existing->id;
        }

        $customer = new Customer;
        $customer->forceFill(['name' => $name, 'synced' => false])->save();
        Audit::log('customers_create', '#' . $customer->id . ' ' . $name . ' (at a sale)');

        return $customer->id;
    }

    // The moment of a sale: now, or the chosen day of an earlier sale at the time of day it is entered. The sale keeps only the day
    // (`sale_date`); its tickets and a ticket loan keep the moment, so they agree with it.
    private function soldAt(array $data)
    {
        $date = $data['sale_date'] ?? today()->toDateString();

        return $date === today()->toDateString() ? now() : Carbon::parse($date . ' ' . now()->format('H:i:s'));
    }

    // The cost per kg of a sale: the cost the form showed (the default cost of the fuel type, which a user who may see costs can change), or, for a user
    // who may not (the assistant), the default cost of the fuel type at the time of the sale. Nothing sent by such a user is taken.
    private function costOf(array $data, $fuelType, $soldAt)
    {
        if (auth()->user() && auth()->user()->can('costs.view') && isset($data['cost']) && $data['cost'] !== '') {
            return $data['cost'];
        }

        return FuelInbound::costAt($fuelType, $soldAt);
    }

    // A total no sale can have: nearly always a price or a weight typed wrong, said on the price field.
    public static function checkTotal($total)
    {
        if ($total > self::MAX_TOTAL) {
            throw ValidationException::withMessages(['price' => __('sales.total_too_big', ['total' => number_format($total, 2, '.', ''), 'max' => number_format(self::MAX_TOTAL, 2, '.', '')])]);
        }
    }

    // A sale (usage "sale") has a price above 0: a price of 0 would be a free sale. Own use and other use have no amount, so any price does.
    public static function priceAboveZeroForASale()
    {
        return function ($attribute, $value, $fail) {
            if (request('usage') === 'sale' && is_numeric($value) && (float) $value <= 0) {
                $fail(__('sales.price_above_zero'));
            }
        };
    }

    // Shared by both sale types: a ticket_loan usage creates the linked loan, and any usage that
    // leaves an unpaid balance creates a Debt — see App\Models\Debts\TicketLoan / App\Models\Debts\Debt.
    private function afterSaleCreated($sale, $data, $fuelType, $amountKg, $unpaidAmount, $soldAt)
    {
        if ($data['usage'] === 'ticket_loan') {
            TicketLoan::forceCreate([
                'loaned_at' => $soldAt,
                'date' => $sale->sale_date,
                'customer_id' => $data['customer_id'],
                'fuel_type' => $fuelType,
                'amount_kg' => $amountKg,
                'sale_id' => $sale->id,
                'user_id' => auth()->id(),
            ]);
        }

        if ($unpaidAmount > 0.01) {
            Debt::forceCreate([
                'customer_id' => $data['customer_id'],
                'sale_id' => $sale->id,
                'amount' => $unpaidAmount,
                'payment_method' => $sale->payment_method,
                'user_id' => auth()->id(),
            ]);
        }
    }

    private function defaultCosts()
    {
        return collect(array_keys(config('fuel_types')))->mapWithKeys(function ($fuelType) {
            return [$fuelType => FuelInbound::defaultCost($fuelType)];
        });
    }

    // The first 7 digits (sign and code) of the barcodes of every fuel type and spec, [fuel][spec] => '8676103': the form shows the start and
    // the end of an item as full barcodes, which are these digits and the serial padded to 9 digits.
    private function barcodePrefixes()
    {
        $prefixes = [];
        foreach (array_keys(config('fuel_types')) as $fuelType) {
            foreach (array_keys(config('ticket_specs')) as $spec) {
                if (Barcode::codeFor($fuelType, $spec) !== null) {
                    $prefixes[$fuelType][$spec] = substr(Barcode::make($fuelType, $spec, 0), 0, 7);
                }
            }
        }

        return $prefixes;
    }

    // The published tickets of every fuel type and spec as runs of consecutive serials, [fuel][spec] => [[first, last], ...] (lowest first), for
    // the sale form to show which serials an item will take.
    private function availableSerials()
    {
        $runs = [];
        $tickets = Ticket::where('status', config('ticket_statuses.publish'))->whereNotNull('spec')
            ->orderBy('fuel_type')->orderBy('spec')->orderBy('serial')->select('fuel_type', 'spec', 'serial')->cursor();

        foreach ($tickets as $ticket) {
            $list = isset($runs[$ticket->fuel_type][(int) $ticket->spec]) ? $runs[$ticket->fuel_type][(int) $ticket->spec] : [];
            $last = count($list) - 1;
            if ($last >= 0 && $list[$last][1] + 1 === (int) $ticket->serial) {
                $list[$last][1] = (int) $ticket->serial;
            } else {
                $list[] = [(int) $ticket->serial, (int) $ticket->serial];
            }
            $runs[$ticket->fuel_type][(int) $ticket->spec] = $list;
        }

        return $runs;
    }

    private function availableStock()
    {
        return Ticket::where('status', config('ticket_statuses.publish'))
            ->selectRaw('fuel_type, spec, COUNT(*) AS n')
            ->groupBy('fuel_type', 'spec')
            ->get()
            ->map(function ($r) {
                return (object) ['fuel_type' => $r->fuel_type, 'spec' => (int) $r->spec, 'available' => (int) $r->n];
            })
            ->sortBy('fuel_type')->values();
    }
}
