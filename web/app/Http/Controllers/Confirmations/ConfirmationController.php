<?php

namespace App\Http\Controllers\Confirmations;

use App\Http\Controllers\Controller;
use App\Jobs\RunBackupDump;
use App\Models\Cards\Card;
use App\Models\Debts\DebtPayment;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelOutbound;
use App\Models\Fuel\Tank;
use App\Models\Sales\Customer;
use App\Models\Fuel\Nozzle;
use App\Models\Supplements\Remainder;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketInbound;
use App\Models\Tickets\TicketPublish;
use App\Support\Admin\Audit;
use App\Support\Admin\BackupRun;
use App\Support\Cards\CardLedger;
use App\Support\Reports\ReportSheets;
use App\Support\Sales\SaleDeletion;
use App\Support\Reports\XlsxWriter;
use App\Support\Tickets\Barcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Daily confirmation: the accountant (or manager) picks a date, reviews the sales, the supplements and the blank ticket stock (ticket inbounds and
// publishes) of that date, checks the ones not confirmed
// yet and confirms them. Every record of the date stays in the tables with its state (confirmed or not); a record that has a problem is
// changed (see SaleEditController: its date, comment, receipt) and so leaves that date. A confirmed sale can no longer be deleted or edited
// (a manager can take a confirmation back, see UnconfirmController); later corrections are made with a return entry.
class ConfirmationController extends Controller
{
    // The three pages (Sales, Supplements, Summary) share the date chosen at the top and the list of the dates still waiting.
    private function page(Request $request)
    {
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d|before_or_equal:today']);

        $waiting = $this->waitingByDate();
        // Without a chosen date: today (the waiting dates under the date box lead to the older ones).
        $date = $data['date'] ?? today()->toDateString();

        return [
            'date' => $date,
            'waiting' => $waiting,
            'confirmedSales' => Sale::where('sale_date', $date)->whereNotNull('confirmed_at')->count(),
            'confirmedSupplements' => Supplement::where('supply_date', $date)->whereNotNull('confirmed_at')->count(),
            'confirmedTickets' => TicketInbound::where('inbound_date', $date)->whereNotNull('confirmed_at')->count() + TicketPublish::where('date', $date)->whereNotNull('confirmed_at')->count(),
            'confirmedFuel' => FuelInbound::where('inbound_date', $date)->whereNotNull('confirmed_at')->count()
                + FuelOutbound::whereNull('supplement_id')->where('outbound_date', $date)->whereNotNull('confirmed_at')->count(),
            'confirmedDebts' => DebtPayment::where('paid_date', $date)->whereNotNull('confirmed_at')->count()
                + TicketLoanSettlement::where('date', $date)->whereNotNull('confirmed_at')->count(),
            'deletedSales' => Sale::onlyTrashed()->where('sale_date', $date)->count(),
            'fuels' => config('fuel_types'),
        ];
    }

    // The fuel types in the order of config/fuel_types.php (the first on the left, the second on the right), then any other found in the rows.
    private function fuelKeys($rows)
    {
        return array_values(array_unique(array_merge(array_keys(config('fuel_types')), $rows->pluck('fuel_type')->all())));
    }

    // The sales waiting, in the four blocks of the Sales page: ticket sales, spot sales and card sales (the top-ups of the cards: the sales of fuel, by
    // how they were made) and others (self use, given away, ticket loan). Spot is shown as one table with a part per fuel type (the first on the
    // left, the second on the right); ticket, card and others as one table with a fuel column.
    private function saleBlocks($sales)
    {
        $isSale = function ($sale) {
            return $sale->usage === 'sale';
        };
        $blocks = [
            'ticket' => $sales->filter(function ($sale) use ($isSale) {
                return $isSale($sale) && $sale->sale_type === 'ticket';
            }),
            'spot' => $sales->filter(function ($sale) use ($isSale) {
                return $isSale($sale) && $sale->sale_type === 'spot';
            }),
            'card' => $sales->filter(function ($sale) use ($isSale) {
                return $isSale($sale) && $sale->sale_type === 'card';
            }),
            'others' => $sales->reject($isSale),
        ];

        $fuels = config('fuel_types');
        $groups = [];
        foreach ($blocks as $name => $rows) {
            // `sides`: the parts one table is made of, side by side. `detail`: what the columns say about each sale: its payment (a sale of fuel) or
            // its sale type and usage (the others).
            $groups[$name] = ['count' => $rows->count(), 'rows' => $rows->values(), 'fuels' => [], 'sides' => []];
            if ($name !== 'spot') {
                $groups[$name]['sides'][] = [
                    'title' => null, 'rows' => $groups[$name]['rows'], 'withFuel' => true, 'detail' => $name === 'others' ? 'usage' : 'payment',
                    'target' => 'check-sales-' . $name,
                    // ticket sales: a column for the number of tickets of each spec (kg) any fuel type sells, in the order of the specs
                    'specs' => $name === 'ticket' ? $this->ticketSpecs() : [],
                ];
                continue;
            }
            foreach ($this->fuelKeys($sales) as $fuel) {
                $part = $rows->where('fuel_type', $fuel)->values();
                $groups[$name]['fuels'][$fuel] = $part;
                $groups[$name]['sides'][] = [
                    'title' => ($fuels[$fuel] ?? $fuel) . ' (' . $part->count() . ')', 'rows' => $part, 'withFuel' => false, 'detail' => 'payment',
                    'target' => 'check-sales-' . $name . '-' . $fuel,
                ];
            }
        }

        return $groups;
    }

    // spec key => kg of every spec a fuel type sells tickets at (config/ticket_codes.php), in the order of the specs: gasoline 5/10/15, diesel
    // 5/10/15/50 are the columns 5, 10, 15 and 50.
    private function ticketSpecs()
    {
        $keys = [];
        foreach (config('ticket_codes') as $bySpec) {
            $keys = array_merge($keys, array_keys($bySpec));
        }
        $keys = array_unique($keys);
        sort($keys);

        $specs = [];
        foreach ($keys as $key) {
            $specs[$key] = config('ticket_specs.' . $key);
        }

        return $specs;
    }

    // Sales: the sales of the date, as tabs Ticket sales / Spot sales / Card sales / Others, each with its own confirm button (it confirms the
    // ones checked that are not confirmed yet).
    public function index(Request $request)
    {
        $page = $this->page($request);
        $sales = Sale::with(['items', 'returnedTickets'])->where('sale_date', $page['date'])->orderBy('created_at')->get();

        return view('confirmation.index', $page + [
            'sales' => $sales,
            'groups' => $this->saleBlocks($sales),
            'customers' => Customer::withTrashed()->pluck('name', 'id'),
            'cards' => Card::withTrashed()->pluck('holder_name', 'id'),
        ]);
    }

    // One tab of the Sales page as an .xlsx file: one sheet with the same table (the fuel types side by side) of the sales of the date.
    public function salesExport(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'block' => 'required|in:ticket,spot,card,others',
        ]);

        $sales = Sale::with(['items', 'returnedTickets'])->where('sale_date', $data['date'])->orderBy('created_at')->get();
        $group = $this->saleBlocks($sales)[$data['block']];
        $title = $data['block'] === 'others' ? __('confirm.tab_others') : __('menu.' . $data['block'] . '_sales');

        $path = XlsxWriter::build(ReportSheets::confirmationSales([
            'title' => $title, 'sides' => $group['sides'], 'fuels' => config('fuel_types'),
            'customers' => Customer::withTrashed()->pluck('name', 'id'), 'cards' => Card::withTrashed()->pluck('holder_name', 'id'),
        ]));
        $file = 'confirmation-sales-' . $data['date'] . '-' . $data['block'] . '.xlsx';

        return response()->download($path, $file, ['Content-Type' => XlsxWriter::MIME])->deleteFileAfterSend(true);
    }

    // Supplements: the supplements of the date, the first fuel type on the left and the second on the right.
    public function supplements(Request $request)
    {
        $page = $this->page($request);
        list($supplements, $byFuel) = $this->supplementsOf($page['date']);

        return view('confirmation.supplements', $page + [
            'supplements' => $supplements,
            'byFuel' => $byFuel,
            'nozzleLabels' => Nozzle::labels(),
            // the Remainders tab: the remainders of the date (they are not confirmed, only listed), with the supply each came from
            'remainders' => $this->remaindersOf($page['date']),
        ]);
    }

    private function remaindersOf($date)
    {
        return Remainder::with(['supplement' => function ($query) {
            $query->withTrashed();
        }])->where('remainder_date', $date)->orderBy('created_at')->get();
    }

    // The Remainders tab as an .xlsx file: the remainders generated on the date, with the supply each came from.
    public function remaindersExport(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|before_or_equal:today']);

        $path = XlsxWriter::build(ReportSheets::confirmationRemainders([
            'title' => __('menu.remainders'), 'rows' => $this->remaindersOf($data['date']), 'fuels' => config('fuel_types'), 'nozzleLabels' => Nozzle::labels(),
        ]));

        return response()->download($path, 'confirmation-remainders-' . $data['date'] . '.xlsx', ['Content-Type' => XlsxWriter::MIME])->deleteFileAfterSend(true);
    }

    // The supplements of the date, and the same split by fuel type (the first on the left, the second on the right).
    private function supplementsOf($date)
    {
        $supplements = Supplement::with('remainder')->where('supply_date', $date)->orderBy('supplied_at')->get();

        $byFuel = [];
        foreach ($this->fuelKeys($supplements) as $fuel) {
            $byFuel[$fuel] = $supplements->where('fuel_type', $fuel)->values();
        }

        return [$supplements, $byFuel];
    }

    // The table of the Supplements page as an .xlsx file: one sheet, the fuel types side by side, of the supplements of the date.
    public function supplementsExport(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|before_or_equal:today']);

        list($supplements, $byFuel) = $this->supplementsOf($data['date']);
        $fuels = config('fuel_types');
        $sides = [];
        foreach ($byFuel as $fuel => $rows) {
            $sides[] = ['title' => ($fuels[$fuel] ?? $fuel) . ' (' . $rows->count() . ')', 'rows' => $rows];
        }

        $path = XlsxWriter::build(ReportSheets::confirmationSupplements(['title' => __('confirm.supplements'), 'sides' => $sides, 'nozzleLabels' => Nozzle::labels()]));

        return response()->download($path, 'confirmation-supplements-' . $data['date'] . '.xlsx', ['Content-Type' => XlsxWriter::MIME])->deleteFileAfterSend(true);
    }

    // Ticket stock: the ticket inbounds (by their date) and the publishes (by their date) of the date, to be checked and confirmed like the sales. A confirmed
    // inbound can no longer be changed or deleted (see TicketInboundController); a manager can take the confirmation back (UnconfirmController).
    public function tickets(Request $request)
    {
        $page = $this->page($request);

        return view('confirmation.tickets', $page + [
            'inbounds' => TicketInbound::where('inbound_date', $page['date'])->orderBy('id')->get(),
            'publishes' => TicketPublish::where('date', $page['date'])->orderBy('id')->get(),
            'specs' => config('ticket_specs'),
        ]);
    }

    // Fuel stock: the fuel inbounds of the date and the fuel outbounds made by hand (the outbound of a supply follows the supply, which is confirmed on its own page).
    public function fuel(Request $request)
    {
        $page = $this->page($request);

        return view('confirmation.fuel', $page + [
            'inbounds' => FuelInbound::where('inbound_date', $page['date'])->orderBy('id')->get(),
            'outbounds' => FuelOutbound::whereNull('supplement_id')->where('outbound_date', $page['date'])->orderBy('id')->get(),
            'tanks' => Tank::withTrashed()->pluck('name', 'id'),
        ]);
    }

    // Debts: the debt payments received on the date (a reversal is a line of its own, with the negative amount) and the settlements of the ticket loans of the date
    // (the tickets given back; a reversal has the negative kg). The debts and the ticket loans themselves are not confirmed.
    public function debts(Request $request)
    {
        $page = $this->page($request);

        return view('confirmation.debts', $page + [
            'payments' => DebtPayment::with('debt.sale')->where('paid_date', $page['date'])->orderBy('id')->get(),
            'settlements' => TicketLoanSettlement::with('loan')->where('date', $page['date'])->orderBy('settled_at')->get(),
            'customers' => Customer::withTrashed()->pluck('name', 'id'),
        ]);
    }

    // Overview: for the date, each area of the daily confirmation with how many records it has, how many are confirmed and how many wait, and the things to look at
    // before confirming (a terminal that has not uploaded, a sale without its cost, a supply without tickets, deleted sales waiting to be erased).
    public function overview(Request $request)
    {
        $page = $this->page($request);
        $date = $page['date'];

        $count = function ($query) {
            $total = (clone $query)->count();
            $waiting = (clone $query)->whereNull('confirmed_at')->count();

            return ['total' => $total, 'confirmed' => $total - $waiting, 'waiting' => $waiting];
        };
        $add = function (array $parts) {
            $sum = ['total' => 0, 'confirmed' => 0, 'waiting' => 0];
            foreach ($parts as $part) {
                foreach ($sum as $key => $value) {
                    $sum[$key] += $part[$key];
                }
            }

            return $sum;
        };
        $areas = [
            'fuel' => ['confirmations.fuel', $add([
                $count(FuelInbound::where('inbound_date', $date)), $count(FuelOutbound::whereNull('supplement_id')->where('outbound_date', $date)),
            ])],
            'tickets' => ['confirmations.tickets', $add([
                $count(TicketInbound::where('inbound_date', $date)), $count(TicketPublish::where('date', $date)),
            ])],
            'sales' => ['confirmations.index', $count(Sale::where('sale_date', $date))],
            'supplements' => ['confirmations.supplements', $count(Supplement::where('supply_date', $date))],
            'debts' => ['confirmations.debts', $add([
                $count(DebtPayment::where('paid_date', $date)), $count(TicketLoanSettlement::where('date', $date)),
            ])],
        ];

        // what to look at: the terminals matter once the business day is over (until then they simply have not finished it)
        list($silent, $dayEnds) = BackupRun::silentDevices($date);
        $warnings = [
            'silent' => $dayEnds->isFuture() ? [] : $silent->all(),
            'dayEnds' => $dayEnds,
            'noCost' => Sale::where('sale_date', $date)->whereNull('cost')->whereNull('reverses_sale_id')->whereIn('sale_type', ['ticket', 'spot'])->count(),
            'noTickets' => Supplement::where('supply_date', $date)->whereNull('card_id')->whereDoesntHave('tickets')->count(),
            'deleted' => $page['deletedSales'],
            'unusedTickets' => $this->unusedTickets($date),
            'supplyChecks' => $this->supplyChecks($date),
        ];

        return view('confirmation.overview', $page + ['areas' => $areas, 'warnings' => $warnings]);
    }

    // The supplies of the date whose figures do not agree, each as ['id', 'text']. A supply is a fact (the fuel came out), so none of this is refused on upload; it is
    // shown here for the accountant to look at before confirming:
    // - the tickets of the supply do not add up to its preset (a ticket has not been uploaded yet, or a wrong one is on it)
    // - more than one remainder came out of it, or the remainder is not what was left of the tickets (the same 0.05 kg tolerance as the terminal), or what was left
    //   is nowhere (no remainder, and not given back to a card)
    private function supplyChecks($date)
    {
        $nozzles = Nozzle::labels();
        $kgOf = function ($ticket) {
            return $ticket->spec !== null ? (float) config('ticket_specs.' . $ticket->spec) : (float) $ticket->amount_kg;
        };
        $supplements = Supplement::with(['tickets', 'remainder'])->where('supply_date', $date)->whereNull('card_id')->orderBy('supplied_at')->get();
        $remainderCount = Remainder::whereIn('supplement_id', $supplements->pluck('id'))->selectRaw('supplement_id, COUNT(*) AS n')->groupBy('supplement_id')->pluck('n', 'supplement_id');

        $found = [];
        foreach ($supplements as $supplement) {
            if ($supplement->tickets->isEmpty()) {
                continue;        // a supply without tickets has its own note (noTickets)
            }
            $name = substr((string) $supplement->supplied_at, 11, 5) . ' ' . ($nozzles[$supplement->nozzle_id] ?? $supplement->nozzle_id);
            $tickets = round($supplement->tickets->sum($kgOf), 2);
            $preset = round((float) $supplement->preset_kg, 2);
            if (abs($tickets - $preset) > 0.005) {
                $found[] = ['id' => $supplement->id, 'text' => __('confirm.check_tickets', ['supply' => $name, 'tickets' => number_format($tickets, 2), 'preset' => number_format($preset, 2)])];
            }

            $left = round($tickets - (float) $supplement->out_amount_kg, 2);
            $remainder = $supplement->remainder ? round((float) $supplement->remainder->amount_kg, 2) : 0.0;
            $given = CardLedger::paidByCard($supplement) !== null;        // the unused part of a card's ticket is the card's
            if (($remainderCount[$supplement->id] ?? 0) > 1) {
                $found[] = ['id' => $supplement->id, 'text' => __('confirm.check_remainders', ['supply' => $name, 'count' => $remainderCount[$supplement->id]])];
            } elseif (!$given && abs($left - $remainder) > 0.05 && ($remainder > 0 || $left > 0.05)) {
                $found[] = ['id' => $supplement->id, 'text' => __('confirm.check_remainder', ['supply' => $name, 'left' => number_format($left, 2), 'remainder' => number_format($remainder, 2)])];
            }
        }

        return $found;
    }

    // The tickets a POS issued on the date (a spot sale, self use, other use, ticket loan or a card) that nobody has supplied yet, by kind. Nothing of them is
    // left to confirm (the sale or the card top-up is), but a card has paid for such a ticket, so the accountant should see them. A remainder ticket is left
    // out: it waits for its supply by design.
    private function unusedTickets($date)
    {
        $counts = Ticket::whereIn('code', Barcode::posCodes())->where('status', config('ticket_statuses.sale'))
            ->where('sale_date', '>=', $date . ' 00:00:00')->where('sale_date', '<=', $date . ' 23:59:59')
            ->selectRaw('code, COUNT(*) AS n')->groupBy('code')->pluck('n', 'code');

        $kinds = [];
        foreach (['direct', 'card', 'self', 'other', 'ticket_loan'] as $type) {
            foreach ($counts as $code => $n) {
                if (Barcode::posType($code) === $type) {
                    $kinds[$type] = ($kinds[$type] ?? 0) + (int) $n;
                }
            }
        }

        return $kinds;
    }

    // Summary: the figures of the whole date.
    public function summaryPage(Request $request)
    {
        $page = $this->page($request);

        return view('confirmation.summary', $page + [
            'summary' => $this->summary($page['date']),
            'nozzleLabels' => Nozzle::labels(),
        ]);
    }

    // The Summary page as an .xlsx file: a sheet each for the sales, the supplements and the remainders of the date.
    public function summaryExport(Request $request)
    {
        $page = $this->page($request);

        $path = XlsxWriter::build(ReportSheets::confirmationSummary([
            'date' => $page['date'], 'summary' => $this->summary($page['date']), 'fuels' => $page['fuels'],
        ]));

        return response()->download($path, 'confirmation-summary-' . $page['date'] . '.xlsx', ['Content-Type' => XlsxWriter::MIME])->deleteFileAfterSend(true);
    }

    // The figures of the whole date, confirmed or not (deleted sales are not counted), in three sections:
    // - sales: ticket sales, spot sales, self use and the others (given away, ticket loan), by fuel type
    // - supplements: by the kind of the tickets they used (tickets of the published stock, spot, remainder, self use, others: given away /
    //   ticket loan), by fuel type
    // - remainders: made on the date and used up on it, by fuel type
    // The money of the day by how it was paid (cash, bank): what the sales received, what the returns gave back, the debt payments received and reversed, the net of those, and
    // what was sold on credit (not paid). Deleted sales are not counted. It is the figure to check the cash of the cashier and the bank against.
    private function money($date)
    {
        $sales = Sale::where('sale_date', $date)->get(['payment_method', 'paid_amount', 'unpaid_amount']);
        $payments = DebtPayment::where('paid_date', $date)->get(['payment_method', 'amount']);

        $rows = [];
        foreach (['cash', 'bank'] as $method) {
            $s = $sales->where('payment_method', $method);
            $p = $payments->where('payment_method', $method);
            $row = [
                'sales_received' => round((float) $s->where('paid_amount', '>', 0)->sum('paid_amount'), 2),
                'sales_refunded' => round((float) $s->where('paid_amount', '<', 0)->sum('paid_amount'), 2),
                'payments_received' => round((float) $p->where('amount', '>', 0)->sum('amount'), 2),
                'payments_reversed' => round((float) $p->where('amount', '<', 0)->sum('amount'), 2),
            ];
            $row['net'] = round($row['sales_received'] + $row['sales_refunded'] + $row['payments_received'] + $row['payments_reversed'], 2);
            $row['credit'] = round((float) $s->sum('unpaid_amount'), 2);
            $rows[$method] = $row;
        }

        return $rows;
    }

    private function summary($date)
    {
        $sales = Sale::with(['items', 'returnedTickets'])->where('sale_date', $date)->get();
        $supplements = Supplement::with('remainder')->where('supply_date', $date)->orderBy('supplied_at')->get();
        $tickets = Ticket::whereIn('supplement_id', $supplements->pluck('id'))->get();
        $made = Remainder::where('remainder_date', $date)->get();
        $used = Remainder::where('complete_date', $date)->get();

        $fuels = array_values(array_unique(array_merge(
            array_keys(config('fuel_types')), $sales->pluck('fuel_type')->all(), $tickets->pluck('fuel_type')->all(),
            $made->pluck('fuel_type')->all(), $used->pluck('fuel_type')->all()
        )));

        // self use is a kind of its own; given away and ticket loan are the others
        $saleKind = function ($sale) {
            if ($sale->sale_type === 'card') {
                return 'card';           // a top-up of a card
            }
            if ($sale->usage === 'self') {
                return 'self';
            }

            return $sale->usage !== 'sale' ? 'others' : ($sale->sale_type === 'ticket' ? 'ticket' : 'spot');
        };
        // what a ticket is, from its code (a POS ticket: the second digit; a press ticket: a ticket of the stock)
        $ticketKind = function ($ticket) {
            switch (Barcode::posType($ticket->code)) {
                case 'direct':
                    return 'spot';
                case 'remainder':
                    return 'remainder';
                case 'self':
                    return 'self';
                case 'card':
                    return 'card';
                case 'other':
                case 'ticket_loan':
                    return 'others';
            }

            return 'ticket';
        };
        $kgOf = function ($ticket) {
            return $ticket->spec !== null ? (float) config('ticket_specs.' . $ticket->spec) : (float) $ticket->amount_kg;
        };

        $salesBy = [];
        foreach (['ticket', 'spot', 'card', 'self', 'others'] as $kind) {
            foreach ($fuels as $fuel) {
                $rows = $sales->filter(function ($sale) use ($saleKind, $kind, $fuel) {
                    return $saleKind($sale) === $kind && $sale->fuel_type === $fuel;
                });
                $salesBy[$kind][$fuel] = [
                    'count' => $rows->count(), 'kg' => $rows->sum('amount_kg'), 'total' => $rows->sum('total_amount'),
                    'paid' => $rows->sum('paid_amount'), 'unpaid' => $rows->sum('unpaid_amount'),
                ];
                if ($kind === 'ticket') {
                    // the tickets of each spec in these sales, from their items
                    foreach ($this->ticketSpecs() as $spec => $kg) {
                        $salesBy[$kind][$fuel]['specs'][$spec] = (int) $rows->sum(function ($sale) use ($spec) {
                            return $sale->ticketsOfSpec($spec);
                        });
                    }
                }
            }
        }

        // The kind of a supplement is `card` when it was made with a card, otherwise the kind of the tickets it used (a ticket of the stock, a spot
        // ticket, a remainder ticket, self use, others): when it used tickets of several kinds, the kind that covered the most kg; one without tickets yet (a manual supply whose tickets
        // are assigned afterwards) counts as a supplement for tickets.
        $order = ['ticket', 'spot', 'card', 'remainder', 'self', 'others'];
        $kindOf = function ($supplement) use ($tickets, $ticketKind, $kgOf, $order) {
            if ($supplement->card_id) {
                return 'card';           // a supply made with a card has no tickets
            }
            $kg = array_fill_keys($order, 0.0);
            $used = false;
            foreach ($tickets->where('supplement_id', $supplement->id) as $ticket) {
                $kg[$ticketKind($ticket)] += $kgOf($ticket);
                $used = true;
            }
            if (!$used) {
                return 'ticket';
            }
            $best = 'ticket';
            foreach ($order as $kind) {                 // the first of the kinds with the most kg wins a tie
                if ($kg[$kind] > $kg[$best]) {
                    $best = $kind;
                }
            }

            return $best;
        };
        $supplementKinds = [];
        foreach ($supplements as $supplement) {
            $supplementKinds[$supplement->id] = $kindOf($supplement);
        }

        // the supplements of each kind and fuel type: how many, preset kg, out kg, the remainders they gave and their comments
        $supplementsBy = [];
        foreach ($order as $kind) {
            foreach ($fuels as $fuel) {
                $rows = $supplements->filter(function ($supplement) use ($supplementKinds, $kind, $fuel) {
                    return $supplementKinds[$supplement->id] === $kind && $supplement->fuel_type === $fuel;
                });
                $supplementsBy[$kind][$fuel] = [
                    'count' => $rows->count(), 'preset' => $rows->sum('preset_kg'), 'kg' => $rows->sum('out_amount_kg'),
                    'remainder' => $rows->sum(function ($s) {
                        return $s->remainder ? $s->remainder->amount_kg : 0;
                    }),
                    'comment' => $rows->pluck('comment')->filter()->implode('; '),
                ];
            }
        }

        $remaindersBy = [];
        foreach ($fuels as $fuel) {
            $remaindersBy[$fuel] = [
                'made' => $made->where('fuel_type', $fuel)->count(), 'made_kg' => $made->where('fuel_type', $fuel)->sum('amount_kg'),
                'used' => $used->where('fuel_type', $fuel)->count(), 'used_kg' => $used->where('fuel_type', $fuel)->sum('amount_kg'),
            ];
        }

        return [
            'fuelKeys' => $fuels,
            'money' => $this->money($date),
            'ticketSpecs' => $this->ticketSpecs(),
            'salesBy' => $salesBy,
            'remaindersBy' => $remaindersBy,
            'supplementsBy' => $supplementsBy,
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'sales' => 'nullable|array|max:2000',
            'sales.*' => 'uuid',
            'supplements' => 'nullable|array|max:2000',
            'supplements.*' => 'uuid',
            'ticket_inbounds' => 'nullable|array|max:2000',
            'ticket_inbounds.*' => 'integer',
            'ticket_publishes' => 'nullable|array|max:2000',
            'ticket_publishes.*' => 'integer',
            'debt_payments' => 'nullable|array|max:2000',
            'debt_payments.*' => 'integer',
            'ticket_settlements' => 'nullable|array|max:2000',
            'ticket_settlements.*' => 'uuid',
            'fuel_outbounds' => 'nullable|array|max:2000',
            'fuel_outbounds.*' => 'integer',
            'fuel_inbounds' => 'nullable|array|max:2000',
            'fuel_inbounds.*' => 'integer',
            'from' => 'nullable|in:sales,supplements,tickets,fuel,debts', // the page the form was on
            'tab' => 'nullable|in:ticket,spot,card,others', // the tab of the Sales page, to come back to it
        ]);
        $from = $data['from'] ?? 'sales';
        $saleIds = $data['sales'] ?? [];
        $supplementIds = $data['supplements'] ?? [];
        $inboundIds = $data['ticket_inbounds'] ?? [];
        $publishIds = $data['ticket_publishes'] ?? [];
        $paymentIds = $data['debt_payments'] ?? [];
        $fuelInboundIds = $data['fuel_inbounds'] ?? [];
        $fuelOutboundIds = $data['fuel_outbounds'] ?? [];
        $settlementIds = $data['ticket_settlements'] ?? [];

        // Confirming with nothing checked is allowed only to erase the deleted sales waiting on that date.
        if (!$saleIds && !$supplementIds && !$inboundIds && !$publishIds && !$paymentIds && !$fuelInboundIds && !$fuelOutboundIds && !$settlementIds && ($from !== 'sales' || !Sale::onlyTrashed()->where('sale_date', $data['date'])->exists())) {
            throw ValidationException::withMessages([$from => __('confirm.nothing_checked')]);
        }

        // Only records of the chosen date that are still unconfirmed count, so a stale page or a doctored form cannot
        // confirm anything else. The date's deleted sales are erased for good in the same step.
        list($sales, $supplements, $erased, $inbounds, $publishes, $payments, $fuelInbounds, $fuelOutbounds, $settlements) = DB::transaction(function () use ($data, $saleIds, $supplementIds, $inboundIds, $publishIds, $paymentIds, $fuelInboundIds, $fuelOutboundIds, $settlementIds, $from) {
            $stamp = ['confirmed_at' => now(), 'confirmed_by' => auth()->id()];

            $confirm = function ($query, array $ids, $column) use ($data, $stamp) {
                return $ids ? $query->whereIn('id', $ids)->where($column, $data['date'])->whereNull('confirmed_at')->update($stamp) : 0;
            };

            return [
                $confirm(Sale::query(), $saleIds, 'sale_date'),
                $confirm(Supplement::query(), $supplementIds, 'supply_date'),
                $from === 'sales' ? SaleDeletion::purge($data['date']) : 0, // the deleted sales are shown, and erased, on the Sales page
                $confirm(TicketInbound::query(), $inboundIds, 'inbound_date'),
                $confirm(TicketPublish::query(), $publishIds, 'date'),
                $confirm(DebtPayment::query(), $paymentIds, 'paid_date'),
                $confirm(FuelInbound::query(), $fuelInboundIds, 'inbound_date'),
                $confirm(FuelOutbound::whereNull('supplement_id'), $fuelOutboundIds, 'outbound_date'),
                $confirm(TicketLoanSettlement::query(), $settlementIds, 'date'),
            ];
        });

        Audit::log('confirm', $data['date'] . ': ' . $sales . ' sales, ' . $supplements . ' supplements, ' . $inbounds . ' ticket inbounds, ' . $publishes . ' ticket publishes, ' . $payments . ' debt payments, ' . $settlements . ' ticket settlements, ' . $fuelInbounds . ' fuel inbounds, ' . $fuelOutbounds . ' fuel outbounds, ' . $erased . ' deleted sales erased');

        $message = $from === 'tickets'
            ? __('confirm.done_tickets', ['inbounds' => $inbounds, 'publishes' => $publishes, 'date' => $data['date']])
            : ($from === 'fuel'
                ? __('confirm.done_fuel', ['inbounds' => $fuelInbounds, 'outbounds' => $fuelOutbounds, 'date' => $data['date']])
                : ($from === 'debts'
                    ? __('confirm.done_debts', ['payments' => $payments, 'settlements' => $settlements, 'date' => $data['date']])
                    : __('confirm.done', ['sales' => $sales, 'supplements' => $supplements, 'date' => $data['date']])));
        if ($erased) {
            $message .= ' ' . __('confirm.erased', ['count' => $erased]);
        }

        // The day is finished when nothing of it is left to confirm: the database is dumped, after the response, as it stands now. Not before the business day is
        // over and every terminal has uploaded since: one that has not may still hold records of the day, which would reach the web app after the dump.
        if (config('backup.dump_on_confirmation') && ($sales || $supplements || $inbounds || $publishes || $payments || $fuelInbounds || $fuelOutbounds || $settlements) && $this->dayIsFullyConfirmed($data['date'])) {
            list($silent, $dayEnds) = BackupRun::silentDevices($data['date']);
            if ($dayEnds->isFuture()) {
                $message .= ' ' . __('confirm.backup_day_open', ['time' => $dayEnds->format('Y-m-d H:i')]);
            } elseif ($silent->isNotEmpty()) {
                $message .= ' ' . __('confirm.backup_terminals', ['devices' => $silent->implode(', ')]);
            } else {
                RunBackupDump::dispatchAfterResponse('confirmed ' . $data['date']);
                $message .= ' ' . __('confirm.backup_queued');
            }
        }

        $url = route(['sales' => 'confirmations.index', 'supplements' => 'confirmations.supplements', 'tickets' => 'confirmations.tickets', 'fuel' => 'confirmations.fuel', 'debts' => 'confirmations.debts'][$from], ['date' => $data['date']]);

        return redirect($url . ($from === 'sales' && !empty($data['tab']) ? '#tab-' . $data['tab'] : ''))->with('status', $message);
    }

    private function dayIsFullyConfirmed($date)
    {
        return !Sale::where('sale_date', $date)->whereNull('confirmed_at')->exists()
            && !Supplement::where('supply_date', $date)->whereNull('confirmed_at')->exists()
            && !TicketInbound::where('inbound_date', $date)->whereNull('confirmed_at')->exists()
            && !TicketPublish::where('date', $date)->whereNull('confirmed_at')->exists()
            && !DebtPayment::where('paid_date', $date)->whereNull('confirmed_at')->exists()
            && !FuelInbound::where('inbound_date', $date)->whereNull('confirmed_at')->exists()
            && !FuelOutbound::whereNull('supplement_id')->where('outbound_date', $date)->whereNull('confirmed_at')->exists()
            && !TicketLoanSettlement::where('date', $date)->whereNull('confirmed_at')->exists();
    }

    // date => ['sales' => n, 'supplements' => n, 'tickets' => n, 'fuel' => n, 'debts' => n, 'deleted' => n]: records not confirmed yet (tickets: inbounds and publishes; fuel:
    // inbounds and outbounds by hand; debts: payments and ticket settlements) and deleted sales
    // still waiting to be erased, newest date first.
    private function waitingByDate()
    {
        $queries = [
            'sales' => [Sale::whereNull('confirmed_at'), 'sale_date'],
            'supplements' => [Supplement::whereNull('confirmed_at'), 'supply_date'],
            'tickets' => [TicketInbound::whereNull('confirmed_at'), 'inbound_date'],
            'tickets_publish' => [TicketPublish::whereNull('confirmed_at'), 'date'], // (counted with the inbounds: the two are added up)
            'fuel' => [FuelInbound::whereNull('confirmed_at'), 'inbound_date'],
            'fuel_out' => [FuelOutbound::whereNull('supplement_id')->whereNull('confirmed_at'), 'outbound_date'], // (counted with the inbounds)
            'debts' => [DebtPayment::whereNull('confirmed_at'), 'paid_date'],
            'debts_settle' => [TicketLoanSettlement::whereNull('confirmed_at'), 'date'], // (counted with the payments)
            'deleted' => [Sale::onlyTrashed(), 'sale_date'],
        ];

        $waiting = [];
        foreach ($queries as $key => $pair) {
            list($query, $column) = $pair;
            $key = ['tickets_publish' => 'tickets', 'fuel_out' => 'fuel', 'debts_settle' => 'debts'][$key] ?? $key;
            $counts = $query->whereNotNull($column)->selectRaw('`' . $column . '` AS day, COUNT(*) AS n')->groupBy($column)->pluck('n', 'day');
            foreach ($counts as $date => $n) {
                $date = substr((string) $date, 0, 10);
                $waiting[$date] = ($waiting[$date] ?? ['sales' => 0, 'supplements' => 0, 'tickets' => 0, 'fuel' => 0, 'debts' => 0, 'deleted' => 0]);
                $waiting[$date][$key] += (int) $n;
            }
        }
        krsort($waiting);

        return $waiting;
    }
}
