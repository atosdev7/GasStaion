<?php

namespace App\Http\Controllers\Initialize;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Debts\DebtController;
use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Fuel\InitialTankBalance;
use App\Models\Supplements\Remainder;
use App\Models\Fuel\Tank;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Support\Admin\Audit;
use App\Support\Supplements\RemainderMaker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Opening balances at go-live: tank stock (initial_tank_balances), and each customer's opening debt
// or ticket loan, stored as ordinary debts / ticket_loans rows with no sale so payments and loan
// settlements apply to them unchanged. "No sale" is what marks a row as an opening balance.
class BalanceController extends Controller
{
    // The three pages of the Initialize menu: the stock of each tank, the debts and the ticket loans each customer brings at go-live.
    public function tanks()
    {
        return view('balance.tanks', [
            'tanks' => Tank::orderBy('name')->get(),
            'tankBalances' => InitialTankBalance::pluck('amount_kg', 'tank_id'),
            'fuels' => config('fuel_types'),
        ]);
    }

    public function debts()
    {
        return view('balance.debts', [
            'debts' => Debt::whereNull('sale_id')->orderBy('created_at')->get(),
            'paidDebtIds' => DebtPayment::whereNotNull('debt_id')->distinct()->pluck('debt_id')->flip(),
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function loans()
    {
        return view('balance.loans', [
            'loans' => TicketLoan::whereNull('sale_id')->orderBy('loaned_at')->get(),
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
            'fuels' => config('fuel_types'),
        ]);
    }

    // Remainder tickets that existed before the program: the customer holds the ticket, there is no supply behind it (supplement_id NULL).
    // A backup never clears a remainder, so these stay. A wrong entry is stopped by restricting its ticket (Ticket Management).
    public function remainders()
    {
        $remainders = Remainder::whereNull('supplement_id')->orderBy('created_at')->get();

        return view('balance.remainders', [
            'remainders' => $remainders,
            'states' => Ticket::whereIn('barcode', $remainders->pluck('ticket_barcode'))->pluck('status', 'barcode'),
            'fuels' => config('fuel_types'),
        ]);
    }

    public function storeRemainder(Request $request)
    {
        $data = $request->validate([
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'amount_kg' => 'required|numeric|min:0.01|max:99999999',
            'remainder_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'comment' => 'nullable|string|max:255',
        ]);

        $remainder = RemainderMaker::opening($data['fuel_type'], round((float) $data['amount_kg'], 2), $data['remainder_date'] ?? today()->toDateString(), $data['comment'] ?? null);

        Audit::log('opening_remainder', '#' . $remainder->id . ' ' . $remainder->ticket_barcode . ' ' . $remainder->fuel_type . ' ' . $remainder->amount_kg . 'kg');

        return redirect()->route('balances.remainders.index')->with('status', __('initialize.opening_remainder_added', [
            'barcode' => $remainder->ticket_barcode, 'kg' => number_format($remainder->amount_kg, 2, '.', ''),
        ]));
    }

    public function saveTanks(Request $request)
    {
        $tanks = Tank::pluck('name', 'id');
        $data = $request->validate([
            'tanks' => 'array',
            'tanks.*' => 'nullable|numeric|min:0|max:99999999',
        ]);

        foreach ($data['tanks'] ?? [] as $tankId => $amountKg) {
            if (!$tanks->has($tankId)) {
                continue;
            }
            if ($amountKg === null || $amountKg === '') {
                InitialTankBalance::where('tank_id', $tankId)->delete();
            } else {
                InitialTankBalance::updateOrCreate(['tank_id' => $tankId], ['amount_kg' => $amountKg]);
            }
        }

        Audit::log('opening_tank_balances', InitialTankBalance::pluck('amount_kg', 'tank_id')->toJson());

        return redirect()->route('balances.tanks.index')->with('status', __('initialize.saved'));
    }

    public function storeDebt(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'payment_method' => ['required', Rule::in(DebtController::PAYMENT_METHODS)],
        ]);

        $debt = Debt::forceCreate($data + ['sale_id' => null, 'user_id' => auth()->id()]);

        Audit::log('opening_debt', '#' . $debt->id . ' customer ' . $debt->customer_id . ' amount ' . $debt->amount);

        return redirect()->route('balances.debts.index')->with('status', __('initialize.created'));
    }

    public function storeLoan(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'amount_kg' => 'required|numeric|min:0.01|max:99999999',
        ]);

        $loan = TicketLoan::forceCreate($data + [
            'loaned_at' => now(),
            'date' => today()->toDateString(),
            'sale_id' => null,
            'user_id' => auth()->id(),
        ]);

        Audit::log('opening_ticket_loan', '#' . $loan->id . ' customer ' . $loan->customer_id
            . ' ' . $loan->fuel_type . ' ' . $loan->amount_kg . 'kg');

        return redirect()->route('balances.loans.index')->with('status', __('initialize.created'));
    }

    // Only while nothing has been paid or settled against it, so a typo can be fixed without
    // breaking a payment or settlement that already points at the row.
    public function destroyDebt($id)
    {
        $debt = Debt::whereNull('sale_id')->findOrFail($id);
        if (DebtPayment::where('debt_id', $debt->id)->exists()) {
            return redirect()->route('balances.debts.index')->withErrors(['balance' => __('initialize.opening_debt_has_payments')]);
        }

        $debt->delete();
        Audit::log('opening_debt_delete', '#' . $debt->id);

        return redirect()->route('balances.debts.index')->with('status', __('initialize.deleted'));
    }

    public function destroyLoan($id)
    {
        $loan = TicketLoan::whereNull('sale_id')->findOrFail($id);
        if (TicketLoanSettlement::where('loan_id', $loan->id)->exists()) {
            return redirect()->route('balances.loans.index')->withErrors(['balance' => __('initialize.opening_loan_has_settlements')]);
        }

        $loan->delete();
        Audit::log('opening_ticket_loan_delete', '#' . $loan->id);

        return redirect()->route('balances.loans.index')->with('status', __('initialize.deleted'));
    }
}
