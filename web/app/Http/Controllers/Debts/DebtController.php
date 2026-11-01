<?php

namespace App\Http\Controllers\Debts;

use App\Http\Controllers\Controller;
use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Support\Admin\Audit;
use App\Support\Sales\SaleAccess;
use App\Models\Admin\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Analysis screen (what's owed/settled/still open) plus recording extra DebtPayment rows against
// an open debt — or, when it's unclear which of a customer's debts a payment belongs to, against
// just the customer (debt_id left null; see App\Models\Debts\DebtPayment).
class DebtController extends Controller
{
    const PAYMENT_METHODS = ['cash', 'bank'];

    // The filters of the table, in the heads of its columns: the day of the sale, the customer, how it is paid and whether it is open.
    private function filters(Request $request)
    {
        return $request->validate([
            'sale_date' => 'nullable|date_format:Y-m-d',
            'customer' => 'nullable|integer',
            'payment_method' => ['nullable', Rule::in(self::PAYMENT_METHODS)],
            'status' => 'nullable|in:open,settled',
        ]);
    }

    // The filters of the payments tab, in the heads of its columns: the day it was paid, the customer, how it was paid and a part of the note.
    private function paymentFilters(Request $request)
    {
        return $request->validate([
            'paid_date' => 'nullable|date_format:Y-m-d',
            'pay_customer' => 'nullable|integer',
            'pay_method' => ['nullable', Rule::in(self::PAYMENT_METHODS)],
            'note' => 'nullable|string|max:255',
        ]);
    }

    // The debts the filters leave, newest first, with their balances.
    private function filteredDebts(array $filters)
    {
        return $this->debtsWithBalances()->filter(function ($debt) use ($filters) {
            if (!empty($filters['sale_date']) && substr((string) $debt->display_date, 0, 10) !== $filters['sale_date']) {
                return false;
            }
            if (!empty($filters['customer']) && (int) $debt->customer_id !== (int) $filters['customer']) {
                return false;
            }
            if (!empty($filters['payment_method']) && $debt->payment_method !== $filters['payment_method']) {
                return false;
            }
            if (!empty($filters['status'])) {
                $settled = $debt->balance <= 0;
                if (($filters['status'] === 'settled') !== $settled) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request) + $this->paymentFilters($request);
        $debts = $this->filteredDebts($filters);

        // the totals are those of the debts the filters leave; the list is paged by 50
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($debts->forPage($page, 50)->values(), $debts->count(), 50, $page, ['path' => $request->url(), 'query' => ['tab' => 'debts'] + $request->query()]);

        $unallocated = $this->unallocatedByCustomer();
        $unallocatedOf = !empty($filters['pay_customer']) ? $filters['pay_customer'] : (!empty($filters['customer']) ? $filters['customer'] : null);
        if ($unallocatedOf) {
            $unallocated = $unallocated->where('customer_id', (int) $unallocatedOf)->values();
        }

        return view('debt.index', [
            'tab' => in_array($request->query('tab'), ['debts', 'payments'], true) ? $request->query('tab') : 'debts',
            'debts' => $paged,
            'filters' => $filters,
            'unallocated' => $unallocated,
            'customers' => Customer::withTrashed()->orderBy('name')->pluck('name', 'id'),
            // the payments received, newest first (a reversal is a line of its own, with the negative amount)
            'payments' => DebtPayment::with('debt.sale')->when(!empty($filters['paid_date']), function ($q) use ($filters) {
                $q->whereDate('paid_date', $filters['paid_date']);
            })->when(!empty($filters['pay_customer']), function ($q) use ($filters) {
                $q->where('customer_id', (int) $filters['pay_customer']);
            })->when(!empty($filters['pay_method']), function ($q) use ($filters) {
                $q->where('payment_method', $filters['pay_method']);
            })->when(isset($filters['note']) && $filters['note'] !== '', function ($q) use ($filters) {
                $q->where('note', 'like', '%' . addcslashes($filters['note'], '%_\\') . '%');
            })->orderByDesc('paid_date')->orderByDesc('id')->paginate(25, ['*'], 'payments_page')->appends(['tab' => 'payments'] + $request->query()),
            'reversed' => DebtPayment::whereNotNull('reverses_payment_id')->pluck('id', 'reverses_payment_id'),
            'users' => User::withTrashed()->pluck('name', 'id'),
            'totalOwed' => $debts->sum('net_amount'),
            'totalSettled' => $debts->sum('settled_amount'),
            'totalBalance' => $debts->sum('balance'),
        ]);
    }

    // The form of a payment. With ?debt_id the form is made for that debt (the Settle action of the debts list): its customer, the debt itself, its balance as the amount
    // and its payment method are filled in.
    public function create(Request $request)
    {
        $all = $this->debtsWithBalances();
        $openDebts = $all->filter(function ($debt) {
            return $debt->balance > 0.01;
        });

        $preset = [];
        $debt = $request->filled('debt_id') ? $openDebts->firstWhere('id', $request->query('debt_id')) : null;
        if ($debt && Customer::whereKey($debt->customer_id)->exists()) {
            $preset = ['customer_id' => $debt->customer_id, 'debt_id' => $debt->id, 'amount' => round($debt->balance, 2), 'payment_method' => $debt->payment_method];
        }

        return view('debt.create', [
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
            'openDebts' => $openDebts,
            'preset' => $preset,
        ]);
    }

    // One debt: what it is, the sale it came from, the returns that cut it and the payments that settle it (a reversal is a line of its own). A page, or only the detail
    // (for the popup of the list) when asked with modal=1.
    public function show(Request $request, $id)
    {
        $debt = Debt::with(['sale', 'adjustments'])->findOrFail($id);
        $payments = DebtPayment::where('debt_id', $debt->id)->orderBy('paid_date')->orderBy('id')->get();
        $settled = round((float) $payments->sum('amount'), 2);

        $data = [
            'debt' => $debt,
            'customer' => optional(Customer::withTrashed()->find($debt->customer_id))->name,
            'payments' => $payments,
            'reversed' => DebtPayment::whereNotNull('reverses_payment_id')->pluck('id', 'reverses_payment_id'),
            'users' => User::withTrashed()->pluck('name', 'id'),
            'settled' => $settled,
            'balance' => round($debt->net_amount - $settled, 2),
            'saleLink' => $debt->sale && SaleAccess::canSee($request->user(), $debt->sale),
        ];

        return view($request->query('modal') ? 'debt._detail' : 'debt.show', $data);
    }

    // One payment, read only: its fields, its debt and the payment it reverses or that reversed it. A page, or only the detail (for the popup) with modal=1.
    public function showPayment(Request $request, $id)
    {
        $payment = DebtPayment::with('debt')->findOrFail($id);
        $reversal = DebtPayment::where('reverses_payment_id', $payment->id)->first();

        $data = [
            'payment' => $payment,
            'customer' => optional(Customer::withTrashed()->find($payment->customer_id))->name,
            'users' => User::withTrashed()->pluck('name', 'id'),
            'reversedBy' => $reversal ? $reversal->id : null,
        ];

        return view($request->query('modal') ? 'debt._payment' : 'debt.payment', $data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'debt_id' => ['nullable', Rule::exists('debts', 'id')->whereNull('deleted_at')],
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'paid_date' => 'required|date|before_or_equal:today',   // not a day that has not come yet
            'note' => 'nullable|string|max:255',
        ]);

        if (!empty($data['debt_id'])) {
            $belongsToCustomer = Debt::where('id', $data['debt_id'])->where('customer_id', $data['customer_id'])->exists();
            if (!$belongsToCustomer) {
                throw ValidationException::withMessages(['debt_id' => __('debts.debt_customer_mismatch')]);
            }
        }

        // The form pressed twice (or sent again by the browser) must not pay the debt twice: the same payment of the same user in the last 10 seconds is refused.
        $again = DebtPayment::where('customer_id', $data['customer_id'])->where('user_id', auth()->id())->where('amount', $data['amount'])
            ->where('payment_method', $data['payment_method'])->where('paid_date', $data['paid_date'])->where('created_at', '>=', now()->subSeconds(10))
            ->when(!empty($data['debt_id']), function ($q) use ($data) { $q->where('debt_id', $data['debt_id']); }, function ($q) { $q->whereNull('debt_id'); })->exists();
        if ($again) {
            throw ValidationException::withMessages(['amount' => __('debts.payment_twice')]);
        }

        $payment = DebtPayment::forceCreate($data + [
            'debt_id' => $data['debt_id'] ?? null,
            'date' => $data['paid_date'],
            'user_id' => auth()->id(),
        ]);

        Audit::log('debt_payment', '#' . $payment->id . ' customer ' . $payment->customer_id
            . ($payment->debt_id ? ' debt ' . $payment->debt_id : ' (unallocated)') . ' amount ' . $payment->amount);

        // A payment larger than what the debt still owed is kept (the customer may pay ahead) and says so: the debt then shows a credit.
        $response = redirect()->route('payments.index')->with('status', __('debts.created'));
        if ($payment->debt_id) {
            $debt = Debt::with('adjustments')->find($payment->debt_id);
            $balance = round($debt->net_amount - DebtPayment::where('debt_id', $debt->id)->sum('amount'), 2);
            if ($balance < 0) {
                $response->with('warning', __('debts.payment_over', ['credit' => number_format(-$balance, 2)]));
            }
        }

        return $response;
    }

    // Why a payment is not edited (null when it may be): a reversal and a payment that was reversed stay as they are, a confirmed payment is locked until a manager takes
    // its confirmation back.
    private function lockOf(DebtPayment $payment)
    {
        if ($payment->isReversal()) {
            return __('debts.payment_is_reversal');
        }
        if ($payment->isReversed()) {
            return __('debts.payment_already_reversed');
        }
        if ($payment->isConfirmed()) {
            return __('debts.payment_confirmed_locked');
        }

        return null;
    }

    // The form of a payment that was entered wrong: the debt it pays, the amount, the method, the day and the note (the customer stays).
    public function edit($id)
    {
        $payment = DebtPayment::findOrFail($id);
        if ($reason = $this->lockOf($payment)) {
            return redirect()->route('payments.index', ['tab' => 'payments'])->withErrors(['payment' => $reason]);
        }

        // the debts of the customer that are open, and the one the payment is on now
        $debts = $this->debtsWithBalances()->filter(function ($debt) use ($payment) {
            return (int) $debt->customer_id === (int) $payment->customer_id && ($debt->balance > 0.01 || $debt->id === $payment->debt_id);
        });

        return view('debt.edit', [
            'payment' => $payment,
            'customer' => optional(Customer::withTrashed()->find($payment->customer_id))->name,
            'debts' => $debts,
        ]);
    }

    public function update(Request $request, $id)
    {
        $payment = DebtPayment::findOrFail($id);
        if ($reason = $this->lockOf($payment)) {
            return redirect()->route('payments.index', ['tab' => 'payments'])->withErrors(['payment' => $reason]);
        }

        $data = $request->validate([
            'debt_id' => ['nullable', Rule::exists('debts', 'id')->whereNull('deleted_at')],
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'paid_date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:255',
        ]);
        if (!empty($data['debt_id']) && !Debt::where('id', $data['debt_id'])->where('customer_id', $payment->customer_id)->exists()) {
            throw ValidationException::withMessages(['debt_id' => __('debts.debt_customer_mismatch')]);
        }

        $before = '#' . $payment->id . ' ' . $payment->paid_date . ' ' . $payment->amount . ' ' . $payment->payment_method . ' debt ' . ($payment->debt_id ?: '-') . ' / ' . $payment->note;
        $payment->forceFill([
            'debt_id' => $data['debt_id'] ?? null, 'amount' => $data['amount'], 'payment_method' => $data['payment_method'],
            'paid_date' => $data['paid_date'], 'date' => $data['paid_date'], 'note' => $data['note'] ?? null,
        ])->save();

        Audit::log('debt_payment_edit', $before . ' -> ' . $payment->paid_date . ' ' . $payment->amount . ' ' . $payment->payment_method . ' debt ' . ($payment->debt_id ?: '-') . ' / ' . $payment->note);

        $response = redirect()->route('payments.index', ['tab' => 'payments'])->with('status', __('debts.payment_saved'));
        if ($payment->debt_id) {
            $debt = Debt::with('adjustments')->find($payment->debt_id);
            $balance = round($debt->net_amount - DebtPayment::where('debt_id', $debt->id)->sum('amount'), 2);
            if ($balance < 0) {
                $response->with('warning', __('debts.payment_over', ['credit' => number_format(-$balance, 2)]));
            }
        }

        return $response;
    }

    // Undoes a wrong payment by a counter entry: the negative amount, the same customer, debt and method, dated today. The payment stays in the list.
    public function reverse(Request $request, $id)
    {
        $data = $request->validate(['note' => 'nullable|string|max:200']);

        $reversal = DB::transaction(function () use ($id, $data) {
            $payment = DebtPayment::lockForUpdate()->findOrFail($id);
            if ($payment->isReversal()) {
                throw ValidationException::withMessages(['payment' => __('debts.payment_is_reversal')]);
            }
            if ($payment->isReversed()) {
                throw ValidationException::withMessages(['payment' => __('debts.payment_already_reversed')]);
            }
            if ($payment->isConfirmed()) {
                throw ValidationException::withMessages(['payment' => __('debts.payment_confirmed_no_reverse')]);
            }

            return DebtPayment::forceCreate([
                'customer_id' => $payment->customer_id, 'debt_id' => $payment->debt_id, 'amount' => 0 - $payment->amount,
                'payment_method' => $payment->payment_method, 'paid_date' => today()->toDateString(), 'date' => today()->toDateString(),
                'note' => trim('Reversal of #' . $payment->id . ' ' . ($data['note'] ?? '')), 'user_id' => auth()->id(),
                'reverses_payment_id' => $payment->id,
            ]);
        });

        Audit::log('debt_payment_reversal', '#' . $reversal->id . ' reverses #' . $reversal->reverses_payment_id . ' customer ' . $reversal->customer_id . ' amount ' . $reversal->amount);

        return redirect()->route('payments.index')->with('status', __('debts.payment_reversed'));
    }

    // Debt rows with settled_amount/balance computed fresh from debt_payments — never stored (see
    // the 2026-09-23 schema note: a payment isn't always tied to one specific debt, so a stored
    // per-debt total would be unreliable). Shared by index() (shows all) and create() (open only).
    private function debtsWithBalances()
    {
        $settled = DebtPayment::whereNotNull('debt_id')
            ->selectRaw('debt_id, SUM(amount) AS settled')
            ->groupBy('debt_id')
            ->pluck('settled', 'debt_id');

        return Debt::with(['sale', 'adjustments'])->orderByDesc('created_at')->get()->map(function ($debt) use ($settled) {
            $debt->settled_amount = (float) ($settled[$debt->id] ?? 0);
            $debt->balance = $debt->net_amount - $debt->settled_amount;

            return $debt;
        });
    }

    private function unallocatedByCustomer()
    {
        return DebtPayment::whereNull('debt_id')
            ->selectRaw('customer_id, SUM(amount) AS unallocated')
            ->groupBy('customer_id')
            ->get();
    }
}
