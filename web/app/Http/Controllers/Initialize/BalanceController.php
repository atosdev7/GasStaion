<?php

namespace App\Http\Controllers\Initialize;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Debts\DebtController;
use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Fuel\TankBalance;
use App\Models\Supplements\Remainder;
use App\Models\Fuel\Tank;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Admin\Setting;
use App\Models\Tickets\TicketPublish;
use App\Support\Admin\Audit;
use App\Support\Dashboard\TankLevels;
use App\Support\Fuel\DefaultCost;
use App\Support\Tickets\LegacyTickets;
use App\Support\Supplements\RemainderMaker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Opening balances at go-live: tank stock (tank_balances), and each customer's opening debt
// or ticket loan, stored as ordinary debts / ticket_loans rows with no sale so payments and loan
// settlements apply to them unchanged. "No sale" is what marks a row as an opening balance.
class BalanceController extends Controller
{
    // The three pages of the Initialize menu: the stock of each tank, the debts and the ticket loans each customer brings at go-live.
    // The day the system goes live. The press tickets published before it and sold on paper have no rows of their own (see LegacyTickets): they are made when
    // such a ticket is used. The date can be changed, since the range follows it, but a ticket whose row was made keeps what it was made with.
    public function goLive()
    {
        $date = LegacyTickets::goLive();

        return view('balance.golive', [
            'goLive' => $date,
            'oldPublishes' => $date ? TicketPublish::where('date', '<', $date)->count() : 0,
            'madeRows' => $date ? Ticket::whereIn('publish_id', TicketPublish::where('date', '<', $date)->select('id'))->count() : 0,
        ]);
    }

    public function saveGoLive(Request $request)
    {
        $data = $request->validate(['go_live_date' => 'required|date_format:Y-m-d']);

        $before = LegacyTickets::goLive();
        Setting::updateOrCreate(['key' => LegacyTickets::SETTING], ['value' => $data['go_live_date']]);
        Audit::log('go_live_date', ($before ?: '-') . ' -> ' . $data['go_live_date']);

        return redirect()->route('balances.golive')->with('status', __('initialize.go_live_saved'));
    }

    // The balances of the tanks, like the list of the fuel inbounds: a row for every cost a tank holds (entered here, or left by a backup), and under it the
    // summary of each tank.
    public function tanks()
    {
        return view('balance.tanks', [
            'tanks' => Tank::orderBy('name')->pluck('name', 'id'),
            'balances' => TankBalance::orderBy('tank_id')->orderBy('balance_date')->orderBy('id')->get(),
            'fuels' => config('fuel_types'),
            // the summary of each tank: its balances, what came in and went out since, what it holds now (TankLevels)
            'levels' => TankLevels::all(),
            'defaults' => collect(array_keys(config('fuel_types')))->mapWithKeys(function ($fuelType) {
                return [$fuelType => DefaultCost::get($fuelType)];
            }),
        ]);
    }

    public function editTank($id)
    {
        $row = TankBalance::findOrFail($id);

        return view('balance.tank_edit', ['row' => $row, 'tanks' => Tank::orderBy('name')->pluck('name', 'id'), 'fuels' => config('fuel_types')]);
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

    // Fuel a tank holds at one cost, entered like a fuel inbound: the tank (its fuel type is kept on the row), the cost per kg, the balance (kg), the day it counts from
    // and a note. A tank can have several, one for each cost. The cost is the cost of the sales until the first fuel inbound is entered.
    public function storeTank(Request $request)
    {
        $data = $this->tankFields($request);
        $tank = Tank::findOrFail($data['tank_id']);

        // A tank has one row for each cost: a balance of a cost the tank already has is added to that row.
        $same = $this->sameCost($data['tank_id'], $data['cost']);
        // the default cost of the fuel type: this cost when the box is checked; the first cost of a fuel type is its default without it
        if ($request->boolean('is_default')) {
            DefaultCost::set($tank->fuel_type, $data['cost']);
        } else {
            DefaultCost::setIfNone($tank->fuel_type, $data['cost']);
        }
        if ($same) {
            $this->addTo($same, $data['balance'], $data['balance_date'], $data['note']);
            Audit::log('tank_balance_add', '#' . $same->id . ' tank ' . $tank->name . ', +' . $data['balance'] . ' kg at cost ' . $same->cost . ', now ' . $same->balance . ' kg');

            return redirect()->route('balances.tanks.index')->with('status', __('initialize.tank_balance_added_to', [
                'kg' => number_format($data['balance'], 2), 'cost' => number_format($same->cost, 4), 'now' => number_format($same->balance, 2),
            ]));
        }

        $row = TankBalance::create($data + ['fuel_type' => $tank->fuel_type, 'source' => TankBalance::MANUAL, 'user_id' => auth()->id()]);

        Audit::log('tank_balance_create', '#' . $row->id . ' tank ' . $tank->name . ', ' . $row->balance . ' kg, cost ' . $row->cost . ', ' . substr((string) $row->balance_date, 0, 10));

        return redirect()->route('balances.tanks.index')->with('status', __('initialize.saved'));
    }

    // The row of this tank that has this cost, if there is one (the oldest, when an older entry left two).
    private function sameCost($tankId, $cost, $exceptId = null)
    {
        $query = TankBalance::where('tank_id', $tankId)->whereRaw('ROUND(cost, 4) = ?', [round((float) $cost, 4)])->orderBy('id');
        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->first();
    }

    // Puts a balance of the same cost into a row: the kg are added, the day is the newest, and a new comment is added to the comment it has.
    private function addTo(TankBalance $row, $balance, $date, $note)
    {
        $have = $row->balance_date ? substr((string) $row->balance_date, 0, 10) : '';
        $notes = array_filter([$row->note, $note], function ($text) {
            return $text !== null && trim($text) !== '';
        });
        $notes = array_values(array_unique($notes));

        $row->forceFill([
            'balance' => round((float) $row->balance + (float) $balance, 2),
            'balance_date' => max($have, $date),
            'note' => $notes ? mb_substr(implode('; ', $notes), 0, 255) : null,
            'user_id' => auth()->id(),
        ])->save();
    }

    // A row entered by hand is changed as a whole. A row a backup made is what is left of the removed inbounds and outbounds, so its tank, balance and day stay; its
    // cost (one that the backups did not know, from before the costs) and its note can be written.
    public function updateTank(Request $request, $id)
    {
        $row = TankBalance::findOrFail($id);
        $before = $row->tank_id . ' ' . $row->balance . ' kg, cost ' . $row->cost . ', ' . substr((string) $row->balance_date, 0, 10) . ', ' . $row->note;

        if ($row->isManual()) {
            $data = $this->tankFields($request);
            // changed to a cost the tank already has: the balance goes into that row, and this one is gone (a tank has one row for each cost)
            $same = $this->sameCost($data['tank_id'], $data['cost'], $row->id);
            if ($same) {
                $this->addTo($same, $data['balance'], $data['balance_date'], $data['note']);
                $row->delete();
                Audit::log('tank_balance_update', '#' . $row->id . ': ' . $before . ' -> added to #' . $same->id . ', now ' . $same->balance . ' kg at cost ' . $same->cost);

                return redirect()->route('balances.tanks.index')->with('status', __('initialize.tank_balance_added_to', [
                    'kg' => number_format($data['balance'], 2), 'cost' => number_format($same->cost, 4), 'now' => number_format($same->balance, 2),
                ]));
            }
            $row->forceFill($data)->save();
        } else {
            $data = $request->validate(['cost' => 'required|numeric|min:0|max:999999', 'note' => 'nullable|string|max:255']);
            $row->forceFill(['cost' => $data['cost'], 'note' => isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null])->save();
        }

        Audit::log('tank_balance_update', '#' . $row->id . ': ' . $before . ' -> ' . $row->tank_id . ' ' . $row->balance . ' kg, cost ' . $row->cost . ', ' . substr((string) $row->balance_date, 0, 10) . ', ' . $row->note);

        return redirect()->route('balances.tanks.index')->with('status', __('initialize.saved'));
    }

    // The default cost of the fuel type becomes the cost of this balance (copied: the row is not changed).
    public function makeDefaultTank($id)
    {
        $row = TankBalance::findOrFail($id);
        if ($row->cost === null) {
            return redirect()->route('balances.tanks.index')->withErrors(['balance' => __('initialize.tank_balance_no_cost')]);
        }

        DefaultCost::set($row->fuel_type, $row->cost);
        Audit::log('tank_balance_default', '#' . $row->id . ' ' . $row->fuel_type . ' cost ' . $row->cost);

        return redirect()->route('balances.tanks.index')->with('status', __('fuel.default_cost_set', ['cost' => number_format($row->cost, 4)]));
    }

    // Only a row entered by hand: the others are the stock a backup left, and the tank levels count them.
    public function destroyTank($id)
    {
        $row = TankBalance::findOrFail($id);
        if (!$row->isManual()) {
            return redirect()->route('balances.tanks.index')->withErrors(['balance' => __('initialize.tank_balance_from_backup')]);
        }

        $row->delete();
        Audit::log('tank_balance_delete', '#' . $row->id . ' tank ' . $row->tank_id . ', ' . $row->balance . ' kg, cost ' . $row->cost);

        return redirect()->route('balances.tanks.index')->with('status', __('initialize.deleted'));
    }

    private function tankFields(Request $request)
    {
        $data = $request->validate([
            'tank_id' => ['required', Rule::exists('tanks', 'id')->whereNull('deleted_at')],
            'cost' => 'required|numeric|min:0|max:999999',
            'balance' => 'required|numeric|min:0|max:99999999',
            'balance_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'note' => 'nullable|string|max:255',
        ]);
        $data['note'] = isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null;

        return $data;
    }

    public function storeDebt(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'payment_method' => ['required', Rule::in(DebtController::PAYMENT_METHODS)],
            'debt_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',      // the day the debt counts from ("as of": the statements and the aging use it); today when it is not sent
            'comment' => 'nullable|string|max:255',
        ]);
        $data['debt_date'] = $data['debt_date'] ?? today()->toDateString();
        $data['comment'] = isset($data['comment']) && trim($data['comment']) !== '' ? trim($data['comment']) : null;

        $debt = Debt::forceCreate($data + ['sale_id' => null, 'user_id' => auth()->id()]);

        Audit::log('opening_debt', '#' . $debt->id . ' customer ' . $debt->customer_id . ' amount ' . $debt->amount . ' as of ' . $data['debt_date'] . ($data['comment'] ? ' (' . $data['comment'] . ')' : ''));

        return redirect()->route('balances.debts.index')->with('status', __('initialize.created'));
    }

    public function storeLoan(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'amount_kg' => 'required|numeric|min:0.01|max:99999999',
            'date' => 'nullable|date_format:Y-m-d|before_or_equal:today',      // the day the loan counts from ("as of"); today when it is not sent
            'comment' => 'nullable|string|max:255',
        ]);
        $day = $data['date'] ?? today()->toDateString();
        $comment = isset($data['comment']) && trim($data['comment']) !== '' ? trim($data['comment']) : null;
        unset($data['date'], $data['comment']);

        $loan = TicketLoan::forceCreate($data + [
            'loaned_at' => $day === today()->toDateString() ? now() : $day . ' ' . now()->format('H:i:s'),
            'date' => $day,
            'comment' => $comment,
            'sale_id' => null,
            'user_id' => auth()->id(),
        ]);

        Audit::log('opening_ticket_loan', '#' . $loan->id . ' customer ' . $loan->customer_id
            . ' ' . $loan->fuel_type . ' ' . $loan->amount_kg . 'kg as of ' . $day . ($comment ? ' (' . $comment . ')' : ''));

        return redirect()->route('balances.loans.index')->with('status', __('initialize.created'));
    }

    // An opening debt is changed as a whole while nothing has been paid against it; once there is a payment only the "as of" day and the comment can change (the
    // customer, the amount and the payment method are what the payments were made against).
    public function editDebt($id)
    {
        $debt = Debt::whereNull('sale_id')->findOrFail($id);

        return view('balance.debt_edit', [
            'debt' => $debt, 'customers' => Customer::orderBy('name')->pluck('name', 'id'), 'locked' => DebtPayment::where('debt_id', $debt->id)->exists(),
        ]);
    }

    public function updateDebt(Request $request, $id)
    {
        $debt = Debt::whereNull('sale_id')->findOrFail($id);
        $locked = DebtPayment::where('debt_id', $debt->id)->exists();

        $rules = [
            'debt_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'comment' => 'nullable|string|max:255',
        ];
        if (!$locked) {
            $rules += [
                'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
                'amount' => 'required|numeric|min:0.01|max:99999999',
                'payment_method' => ['required', Rule::in(DebtController::PAYMENT_METHODS)],
            ];
        }
        $data = $request->validate($rules);
        $data['comment'] = isset($data['comment']) && trim($data['comment']) !== '' ? trim($data['comment']) : null;

        $before = 'customer ' . $debt->customer_id . ' amount ' . $debt->amount . ' ' . $debt->payment_method . ' as of ' . substr((string) $debt->debt_date, 0, 10) . ' (' . $debt->comment . ')';
        $debt->forceFill($data)->save();
        Audit::log('opening_debt_update', '#' . $debt->id . ': ' . $before . ' -> customer ' . $debt->customer_id . ' amount ' . $debt->amount . ' ' . $debt->payment_method . ' as of ' . $data['debt_date'] . ' (' . $debt->comment . ')');

        return redirect()->route('balances.debts.index')->with('status', __('initialize.saved'));
    }

    // The same for an opening ticket loan: whole while nothing has been settled against it, then only the "as of" day and the comment.
    public function editLoan($id)
    {
        $loan = TicketLoan::whereNull('sale_id')->findOrFail($id);

        return view('balance.loan_edit', [
            'loan' => $loan, 'customers' => Customer::orderBy('name')->pluck('name', 'id'), 'fuels' => config('fuel_types'),
            'locked' => TicketLoanSettlement::where('loan_id', $loan->id)->exists(),
        ]);
    }

    public function updateLoan(Request $request, $id)
    {
        $loan = TicketLoan::whereNull('sale_id')->findOrFail($id);
        $locked = TicketLoanSettlement::where('loan_id', $loan->id)->exists();

        $rules = [
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'comment' => 'nullable|string|max:255',
        ];
        if (!$locked) {
            $rules += [
                'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
                'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
                'amount_kg' => 'required|numeric|min:0.01|max:99999999',
            ];
        }
        $data = $request->validate($rules);
        $data['comment'] = isset($data['comment']) && trim($data['comment']) !== '' ? trim($data['comment']) : null;
        if ($data['date'] !== substr((string) $loan->date, 0, 10)) {
            $data['loaned_at'] = $data['date'] === today()->toDateString() ? now() : $data['date'] . ' ' . substr((string) $loan->loaned_at, 11, 8);
        }

        $before = 'customer ' . $loan->customer_id . ' ' . $loan->fuel_type . ' ' . $loan->amount_kg . 'kg as of ' . substr((string) $loan->date, 0, 10) . ' (' . $loan->comment . ')';
        $loan->forceFill($data)->save();
        Audit::log('opening_ticket_loan_update', '#' . $loan->id . ': ' . $before . ' -> customer ' . $loan->customer_id . ' ' . $loan->fuel_type . ' ' . $loan->amount_kg . 'kg as of ' . $data['date'] . ' (' . $loan->comment . ')');

        return redirect()->route('balances.loans.index')->with('status', __('initialize.saved'));
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
