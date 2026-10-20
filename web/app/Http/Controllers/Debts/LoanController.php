<?php

namespace App\Http\Controllers\Debts;

use App\Http\Controllers\Controller;
use App\Models\Sales\Customer;
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
        $filters = $this->filters($request);
        $loans = $this->filteredLoans($filters);

        // the totals are those of the loans the filters leave; the list is paged by 50
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($loans->forPage($page, 50)->values(), $loans->count(), 50, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('loan.index', [
            'loans' => $paged,
            'filters' => $filters,
            'customers' => Customer::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'fuels' => config('fuel_types'),
            'totalLoaned' => $loans->sum('amount_kg'),
            'totalSettled' => $loans->sum('settled_amount_kg'),
            'totalBalance' => $loans->sum('balance_kg'),
        ]);
    }

    public function create()
    {
        $openLoans = $this->loansWithBalances()->filter(function ($loan) {
            return $loan->balance_kg > 0.01;
        });

        return view('loan.create', [
            'openLoans' => $openLoans,
            'customers' => Customer::pluck('name', 'id'),
            'fuels' => config('fuel_types'),
        ]);
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

        return redirect()->route('loans.index')->with('status', __('debts.settled_loan', [
            'kg' => $settlement->amount_kg,
        ]));
    }

    private function loansWithBalances()
    {
        return TicketLoan::orderByDesc('loaned_at')->get()->map(function ($loan) {
            $loan->balance_kg = $loan->amount_kg - $loan->settled_amount_kg;

            return $loan;
        });
    }
}
