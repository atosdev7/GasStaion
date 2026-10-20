<?php

namespace App\Http\Controllers\Confirmations;

use App\Http\Controllers\Controller;
use App\Jobs\RunBackupDump;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Nozzle;
use App\Models\Remainder;
use App\Models\Sale;
use App\Models\Supplement;
use App\Models\Ticket;
use App\Support\Audit;
use App\Support\ReportSheets;
use App\Support\SaleDeletion;
use App\Support\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Daily confirmation: the accountant (or manager) picks a date, reviews the sales and supplements of that date, checks the ones not confirmed
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
        // Without a chosen date: the oldest one still waiting, since that is what should be confirmed first.
        $date = $data['date'] ?? ($waiting ? min(array_keys($waiting)) : today()->toDateString());

        return [
            'date' => $date,
            'waiting' => $waiting,
            'confirmedSales' => Sale::where('sale_date', $date)->whereNotNull('confirmed_at')->count(),
            'confirmedSupplements' => Supplement::where('supply_date', $date)->whereNotNull('confirmed_at')->count(),
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
        $sales = Sale::with('items')->where('sale_date', $page['date'])->orderBy('created_at')->get();

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

        $sales = Sale::with('items')->where('sale_date', $data['date'])->orderBy('created_at')->get();
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
    private function summary($date)
    {
        $sales = Sale::with('items')->where('sale_date', $date)->get();
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
        $reserved = config('ticket_reserved_codes');
        $ticketKind = function ($ticket) use ($reserved) {
            $code = (string) $ticket->code;
            if ($code === $reserved['direct']) {
                return 'spot';
            }
            if ($code === $reserved['remainder']) {
                return 'remainder';
            }

            if ($code === $reserved['self']) {
                return 'self';
            }

            return in_array($code, [$reserved['other'], $reserved['ticket_loan']], true) ? 'others' : 'ticket';
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
            'from' => 'nullable|in:sales,supplements', // the page the form was on
            'tab' => 'nullable|in:ticket,spot,card,others', // the tab of the Sales page, to come back to it
        ]);
        $from = $data['from'] ?? 'sales';
        $saleIds = $data['sales'] ?? [];
        $supplementIds = $data['supplements'] ?? [];

        // Confirming with nothing checked is allowed only to erase the deleted sales waiting on that date.
        if (!$saleIds && !$supplementIds && ($from === 'supplements' || !Sale::onlyTrashed()->where('sale_date', $data['date'])->exists())) {
            throw ValidationException::withMessages([$from => __('confirm.nothing_checked')]);
        }

        // Only records of the chosen date that are still unconfirmed count, so a stale page or a doctored form cannot
        // confirm anything else. The date's deleted sales are erased for good in the same step.
        list($sales, $supplements, $erased) = DB::transaction(function () use ($data, $saleIds, $supplementIds, $from) {
            $stamp = ['confirmed_at' => now(), 'confirmed_by' => auth()->id()];

            $confirm = function ($query, array $ids, $column) use ($data, $stamp) {
                return $ids ? $query->whereIn('id', $ids)->where($column, $data['date'])->whereNull('confirmed_at')->update($stamp) : 0;
            };

            return [
                $confirm(Sale::query(), $saleIds, 'sale_date'),
                $confirm(Supplement::query(), $supplementIds, 'supply_date'),
                $from === 'sales' ? SaleDeletion::purge($data['date']) : 0, // the deleted sales are shown, and erased, on the Sales page
            ];
        });

        Audit::log('confirm', $data['date'] . ': ' . $sales . ' sales, ' . $supplements . ' supplements, ' . $erased . ' deleted sales erased');

        $message = __('confirm.done', ['sales' => $sales, 'supplements' => $supplements, 'date' => $data['date']]);
        if ($erased) {
            $message .= ' ' . __('confirm.erased', ['count' => $erased]);
        }

        // The day is finished when nothing of it is left to confirm: the database is dumped, after the response, as it stands now.
        if (config('backup.dump_on_confirmation') && ($sales || $supplements) && $this->dayIsFullyConfirmed($data['date'])) {
            RunBackupDump::dispatchAfterResponse('confirmed ' . $data['date']);
            $message .= ' ' . __('confirm.backup_queued');
        }

        $url = route($from === 'supplements' ? 'confirmations.supplements' : 'confirmations.index', ['date' => $data['date']]);

        return redirect($url . ($from === 'sales' && !empty($data['tab']) ? '#tab-' . $data['tab'] : ''))->with('status', $message);
    }

    private function dayIsFullyConfirmed($date)
    {
        return !Sale::where('sale_date', $date)->whereNull('confirmed_at')->exists()
            && !Supplement::where('supply_date', $date)->whereNull('confirmed_at')->exists();
    }

    // date => ['sales' => n, 'supplements' => n, 'deleted' => n]: records not confirmed yet and deleted sales
    // still waiting to be erased, newest date first.
    private function waitingByDate()
    {
        $queries = [
            'sales' => [Sale::whereNull('confirmed_at'), 'sale_date'],
            'supplements' => [Supplement::whereNull('confirmed_at'), 'supply_date'],
            'deleted' => [Sale::onlyTrashed(), 'sale_date'],
        ];

        $waiting = [];
        foreach ($queries as $key => $pair) {
            list($query, $column) = $pair;
            $counts = $query->whereNotNull($column)->selectRaw('`' . $column . '` AS day, COUNT(*) AS n')->groupBy($column)->pluck('n', 'day');
            foreach ($counts as $date => $n) {
                $waiting[$date] = ($waiting[$date] ?? ['sales' => 0, 'supplements' => 0, 'deleted' => 0]);
                $waiting[$date][$key] = (int) $n;
            }
        }
        krsort($waiting);

        return $waiting;
    }
}
