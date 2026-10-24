<?php

namespace App\Http\Controllers\Debts;

use App\Http\Controllers\Controller;
use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Support\Admin\Audit;
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
        $filters = $this->filters($request);
        $debts = $this->filteredDebts($filters);

        // the totals are those of the debts the filters leave; the list is paged by 50
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($debts->forPage($page, 50)->values(), $debts->count(), 50, $page, ['path' => $request->url(), 'query' => $request->query()]);

        $unallocated = $this->unallocatedByCustomer();
        if (!empty($filters['customer'])) {
            $unallocated = $unallocated->where('customer_id', (int) $filters['customer'])->values();
        }

        return view('debt.index', [
            'debts' => $paged,
            'filters' => $filters,
            'unallocated' => $unallocated,
            'customers' => Customer::withTrashed()->orderBy('name')->pluck('name', 'id'),
            // the payments received, newest first (a reversal is a line of its own, with the negative amount)
            'payments' => DebtPayment::with('debt.sale')->when(!empty($filters['customer']), function ($q) use ($filters) {
                $q->where('customer_id', (int) $filters['customer']);
            })->orderByDesc('paid_date')->orderByDesc('id')->paginate(25, ['*'], 'payments_page')->appends($request->query()),
            'reversed' => DebtPayment::whereNotNull('reverses_payment_id')->pluck('reverses_payment_id')->flip(),
            'users' => User::withTrashed()->pluck('name', 'id'),
            'totalOwed' => $debts->sum('net_amount'),
            'totalSettled' => $debts->sum('settled_amount'),
            'totalBalance' => $debts->sum('balance'),
        ]);
    }

    public function create()
    {
        $openDebts = $this->debtsWithBalances()->filter(function ($debt) {
            return $debt->balance > 0.01;
        });

        return view('debt.create', [
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
            'openDebts' => $openDebts,
        ]);
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
