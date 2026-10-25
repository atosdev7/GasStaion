<?php

namespace App\Http\Controllers\Debts;

use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use App\Models\Sales\Customer;
use App\Support\Sales\SaleAccess;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Support\Admin\Audit;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Analysis screen (who's borrowed tickets and how much of it is settled) plus recording a
// TicketLoanSettlement — a customer surrendering one physical ticket to pay down a loan.
class LoanController extends Controller
{
    // The filters of the table, in the heads of its columns: the day of the loan, the customer, the fuel type and whether it is open.
    private function filters(Request $request)
    {
        return $request->validate([
            'loan_date' => 'nullable|date_format:Y-m-d',
            'customer' => 'nullable|integer',
            'fuel_type' => 'nullable|string|max:4',
            'status' => 'nullable|in:open,settled',
        ]);
    }

    // The filters of the settlements tab, in the heads of its columns: the day it was surrendered, the customer, the fuel type and a part of the barcode.
    private function settlementFilters(Request $request)
    {
        return $request->validate([
            'settled_on' => 'nullable|date_format:Y-m-d',
            'set_customer' => 'nullable|integer',
            'set_fuel' => 'nullable|string|max:4',
            'barcode' => 'nullable|string|max:16',
        ]);
    }

    // The loans the filters leave, newest first, with their balances. The day of a loan is its `date` (the 9:00 business day), or the day of
    // its time stamp while it has none.
    private function filteredLoans(array $filters)
    {
        return $this->loansWithBalances()->filter(function ($loan) use ($filters) {
            if (!empty($filters['loan_date']) && substr((string) ($loan->date ?: $loan->loaned_at), 0, 10) !== $filters['loan_date']) {
                return false;
            }
            if (!empty($filters['customer']) && (int) $loan->customer_id !== (int) $filters['customer']) {
                return false;
            }
            if (!empty($filters['fuel_type']) && $loan->fuel_type !== $filters['fuel_type']) {
                return false;
            }
            if (!empty($filters['status'])) {
                $settled = $loan->balance_kg <= 0;
                if (($filters['status'] === 'settled') !== $settled) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request) + $this->settlementFilters($request);
        $loans = $this->filteredLoans($filters);

        // the totals are those of the loans the filters leave; the list is paged by 50
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($loans->forPage($page, 50)->values(), $loans->count(), 50, $page, ['path' => $request->url(), 'query' => ['tab' => 'loans'] + $request->query()]);

        return view('loan.index', [
            'tab' => in_array($request->query('tab'), ['loans', 'settlements'], true) ? $request->query('tab') : 'loans',
            'loans' => $paged,
            'filters' => $filters,
            'customers' => Customer::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'fuels' => config('fuel_types'),
            // the tickets surrendered, newest first (a reversal is a line of its own, with the negative kg)
            'settlements' => TicketLoanSettlement::with(['loan' => function ($q) {
                $q->withTrashed();
            }])->when(!empty($filters['settled_on']), function ($q) use ($filters) {
                $q->where(function ($q) use ($filters) {
                    $q->where('date', $filters['settled_on'])->orWhere(function ($q) use ($filters) {
                        $q->whereNull('date')->whereDate('settled_at', $filters['settled_on']);
                    });
                });
            })->when(!empty($filters['set_customer']), function ($q) use ($filters) {
                $q->whereIn('loan_id', TicketLoan::withTrashed()->where('customer_id', (int) $filters['set_customer'])->select('id'));
            })->when(!empty($filters['set_fuel']), function ($q) use ($filters) {
                $q->whereIn('loan_id', TicketLoan::withTrashed()->where('fuel_type', $filters['set_fuel'])->select('id'));
            })->when(isset($filters['barcode']) && $filters['barcode'] !== '', function ($q) use ($filters) {
                $q->where('ticket_barcode', 'like', '%' . addcslashes($filters['barcode'], '%_\\') . '%');
            })->orderByDesc('settled_at')->orderByDesc('id')->paginate(25, ['*'], 'settlements_page')->appends(['tab' => 'settlements'] + $request->query()),
            'reversedSettlements' => TicketLoanSettlement::whereNotNull('reverses_settlement_id')->pluck('id', 'reverses_settlement_id'),
            'users' => User::withTrashed()->pluck('name', 'id'),
            'totalLoaned' => $loans->sum('amount_kg'),
            'totalSettled' => $loans->sum('settled_amount_kg'),
            'totalBalance' => $loans->sum('balance_kg'),
        ]);
    }

    // The form of a settlement. With ?loan_id (the Settle action of the loans list) that loan is the one chosen.
    public function create(Request $request)
    {
        $openLoans = $this->loansWithBalances()->filter(function ($loan) {
            return $loan->balance_kg > 0.01;
        });

        return view('loan.create', [
            'preset' => $openLoans->contains('id', $request->query('loan_id')) ? $request->query('loan_id') : null,
            'openLoans' => $openLoans,
            'customers' => Customer::pluck('name', 'id'),
            'fuels' => config('fuel_types'),
        ]);
    }

    // One loan: what it is, the sale it came from and the tickets surrendered against it (a reversal is a line of its own). A page, or only the detail (for the popup
    // of the list) when asked with modal=1.
    public function show(Request $request, $id)
    {
        $loan = TicketLoan::with('sale')->findOrFail($id);
        $settlements = TicketLoanSettlement::where('loan_id', $loan->id)->orderBy('settled_at')->orderBy('id')->get();
        $settled = round((float) $settlements->sum('amount_kg'), 2);

        $data = [
            'loan' => $loan,
            'customer' => optional(Customer::withTrashed()->find($loan->customer_id))->name,
            'fuels' => config('fuel_types'),
            'settlements' => $settlements,
            'reversed' => TicketLoanSettlement::whereNotNull('reverses_settlement_id')->pluck('id', 'reverses_settlement_id'),
            'users' => User::withTrashed()->pluck('name', 'id'),
            'settled' => $settled,
            'balance' => round($loan->amount_kg - $settled, 2),
            'saleLink' => $loan->sale && $request->user()->can('ticket_sales.view') && SaleAccess::canSee($request->user(), $loan->sale),
        ];

        return view($request->query('modal') ? 'loan._detail' : 'loan.show', $data);
    }

    // One settlement, read only: its fields, its loan and the settlement it reverses or that reversed it. A page, or only the detail (for the popup) with modal=1.
    public function showSettlement(Request $request, $id)
    {
        $settlement = TicketLoanSettlement::with(['loan' => function ($q) {
            $q->withTrashed();
        }])->findOrFail($id);
        $reversal = TicketLoanSettlement::where('reverses_settlement_id', $settlement->id)->first();

        $data = [
            'settlement' => $settlement,
            'customer' => $settlement->loan ? optional(Customer::withTrashed()->find($settlement->loan->customer_id))->name : '',
            'fuels' => config('fuel_types'),
            'users' => User::withTrashed()->pluck('name', 'id'),
            'reversedBy' => $reversal ? $reversal->id : null,
        ];

        return view($request->query('modal') ? 'loan._settlement' : 'loan.settlement', $data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'loan_id' => ['required', Rule::exists('ticket_loans', 'id')->whereNull('deleted_at')],
            'ticket_barcode' => ['required', 'string', 'size:16'],
        ]);

        $settlement = DB::transaction(function () use ($data) {
            $loan = TicketLoan::lockForUpdate()->findOrFail($data['loan_id']);

            // Only a ticket the customer actually holds (already sold, not blank stock, not
            // already used) can be surrendered — otherwise anyone could settle a loan with an
            // unsold ticket and get free fuel.
            $ticket = Ticket::where('barcode', $data['ticket_barcode'])
                ->where('status', config('ticket_statuses.sale'))
                ->lockForUpdate()
                ->first();

            if (!$ticket) {
                throw ValidationException::withMessages(['ticket_barcode' => __('debts.ticket_not_settleable')]);
            }
            if ($ticket->fuel_type !== $loan->fuel_type) {
                throw ValidationException::withMessages(['ticket_barcode' => __('debts.ticket_fuel_type_mismatch')]);
            }

            // The ticket's own kg value, never trusted from the client — a normal ticket's is
            // derived from its spec; a spot-sale 'direct' ticket has no spec, so amount_kg instead.
            $amountKg = $ticket->spec !== null ? config('ticket_specs.' . $ticket->spec) : $ticket->amount_kg;

            $settlement = TicketLoanSettlement::forceCreate([
                'loan_id' => $loan->id,
                'ticket_barcode' => $ticket->barcode,
                'amount_kg' => $amountKg,
                'settled_at' => now(),
                'date' => today()->toDateString(),
                'user_id' => auth()->id(),
            ]);

            $ticket->forceFill([
                'status' => config('ticket_statuses.supply'),
                'supply_date' => now(),
            ])->save();

            // Recomputed fresh from the ledger, not incremented — same reasoning as every other
            // settled/paid total in this app (see App\Models\Debts\Debt).
            $loan->settled_amount_kg = TicketLoanSettlement::where('loan_id', $loan->id)->sum('amount_kg');
            $loan->save();

            return $settlement;
        });

        Audit::log('ticket_loan_settlement', '#' . $settlement->id . ' loan ' . $settlement->loan_id
            . ' ticket ' . $settlement->ticket_barcode . ' ' . $settlement->amount_kg . 'kg');

        // A ticket worth more than what the loan still owed is kept (the customer may bring a bigger ticket) and says so: the loan then shows a credit.
        $response = redirect()->route('loans.index', ['tab' => 'settlements'])->with('status', __('debts.settled_loan', [
            'kg' => $settlement->amount_kg,
        ]));
        $loan = TicketLoan::find($settlement->loan_id);
        $balance = round($loan->amount_kg - $loan->settled_amount_kg, 2);
        if ($balance < 0) {
            $response->with('warning', __('debts.settled_loan_over', ['credit' => number_format(-$balance, 2)]));
        }

        return $response;
    }

    // Undoes a wrong settlement by a counter entry: the negative kg for the same loan and ticket, dated today. The ticket goes back to the customer
    // (sold again, as it was before it was surrendered), unless it was used since.
    public function reverse(Request $request, $id)
    {
        $data = $request->validate(['note' => 'nullable|string|max:200']);

        $reversal = DB::transaction(function () use ($id, $data) {
            $settlement = TicketLoanSettlement::lockForUpdate()->findOrFail($id);
            if ($settlement->isReversal()) {
                throw ValidationException::withMessages(['settlement' => __('debts.settlement_is_reversal')]);
            }
            if ($settlement->isReversed()) {
                throw ValidationException::withMessages(['settlement' => __('debts.settlement_already_reversed')]);
            }
            if ($settlement->isConfirmed()) {
                throw ValidationException::withMessages(['settlement' => __('debts.settlement_confirmed_no_reverse')]);
            }

            $loan = TicketLoan::withTrashed()->lockForUpdate()->findOrFail($settlement->loan_id);
            $ticket = Ticket::where('barcode', $settlement->ticket_barcode)->lockForUpdate()->first();
            if ($ticket) {
                // surrendered, not supplied at a nozzle: only then does it go back
                if ((int) $ticket->status !== config('ticket_statuses.supply') || $ticket->supplement_id !== null) {
                    throw ValidationException::withMessages(['settlement' => __('debts.settlement_ticket_used')]);
                }
                $ticket->forceFill(['status' => config('ticket_statuses.sale'), 'supply_date' => null])->save();
            }

            $reversal = TicketLoanSettlement::forceCreate([
                'loan_id' => $loan->id, 'ticket_barcode' => $settlement->ticket_barcode, 'amount_kg' => 0 - $settlement->amount_kg,
                'settled_at' => now(), 'date' => today()->toDateString(), 'comment' => trim('Reversal ' . ($data['note'] ?? '')),
                'user_id' => auth()->id(), 'reverses_settlement_id' => $settlement->id,
            ]);

            $loan->settled_amount_kg = TicketLoanSettlement::where('loan_id', $loan->id)->sum('amount_kg');
            $loan->save();

            return $reversal;
        });

        Audit::log('ticket_loan_settlement_reversal', '#' . $reversal->id . ' reverses ' . $reversal->reverses_settlement_id . ' loan ' . $reversal->loan_id
            . ' ticket ' . $reversal->ticket_barcode . ' ' . $reversal->amount_kg . 'kg');

        return redirect()->route('loans.index', ['tab' => 'settlements'])->with('status', __('debts.settlement_reversed'));
    }

    private function loansWithBalances()
    {
        return TicketLoan::orderByDesc('loaned_at')->get()->map(function ($loan) {
            $loan->balance_kg = $loan->amount_kg - $loan->settled_amount_kg;

            return $loan;
        });
    }
}
