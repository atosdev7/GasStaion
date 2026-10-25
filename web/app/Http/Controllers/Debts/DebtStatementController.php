<?php

namespace App\Http\Controllers\Debts;

use App\Http\Controllers\Controller;
use App\Models\Debts\DebtAdjustment;
use App\Models\Debts\DebtPayment;
use App\Models\Sales\Customer;
use App\Support\Debts\CustomerBalances;
use Carbon\Carbon;
use Illuminate\Http\Request;

// The statement of one customer for a period, to look at, print or hand over: what was owed at the start, each debt made (+), each return that cut a debt (-) and each payment
// received (-, a reversal +) in the order of the days with the running balance, and what is owed at the end; and below it the debts still open today with their age. The payments not
// tied to a debt are in the running balance (they are what the customer paid).
class DebtStatementController extends Controller
{
    public function show(Request $request)
    {
        $input = $request->validate([
            'customer' => 'nullable|integer|exists:customers,id',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);
        $from = Carbon::parse($input['from'] ?? today()->startOfMonth())->toDateString();
        $to = Carbon::parse($input['to'] ?? today())->toDateString();
        $customers = Customer::withTrashed()->orderBy('name')->pluck('name', 'id');

        $data = ['from' => $from, 'to' => $to, 'customers' => $customers, 'customer' => null, 'entries' => []];
        if (empty($input['customer'])) {
            return view('debt_statement.show', $data);
        }

        $customerId = (int) $input['customer'];
        $entries = [];
        foreach (CustomerBalances::debtsWithBalances($customerId) as $debt) {
            $entries[] = ['day' => CustomerBalances::debtDay($debt), 'order' => 0, 'kind' => $debt->sale_id === null ? 'opening' : 'debt', 'method' => $debt->payment_method,
                'amount' => (float) $debt->amount, 'note' => '', 'sale_id' => $debt->sale_id, 'debt' => $debt];
        }
        foreach (DebtAdjustment::where('customer_id', $customerId)->get() as $adjustment) {
            $entries[] = ['day' => substr((string) $adjustment->adjusted_date, 0, 10), 'order' => 1, 'kind' => 'return', 'method' => null,
                'amount' => (float) $adjustment->amount, 'note' => '', 'sale_id' => null, 'debt' => null];
        }
        foreach (DebtPayment::where('customer_id', $customerId)->orderBy('id')->get() as $payment) {
            $entries[] = ['day' => substr((string) $payment->paid_date, 0, 10), 'order' => 2, 'kind' => $payment->reverses_payment_id ? 'reversal' : ($payment->debt_id ? 'payment' : 'general'),
                'method' => $payment->payment_method, 'amount' => 0 - (float) $payment->amount, 'note' => (string) $payment->note, 'sale_id' => null, 'debt' => null];
        }

        // owed at the start: everything before the first day; then the lines of the period in the order of the days
        $opening = 0.0;
        $lines = [];
        usort($entries, function ($a, $b) {
            return [$a['day'], $a['order']] <=> [$b['day'], $b['order']];
        });
        foreach ($entries as $entry) {
            if ($entry['day'] < $from) {
                $opening += $entry['amount'];
            } elseif ($entry['day'] <= $to) {
                $lines[] = $entry;
            }
        }
        $balance = round($opening, 2);
        foreach ($lines as &$line) {
            $balance = round($balance + $line['amount'], 2);
            $line['balance'] = $balance;
        }
        unset($line);

        $open = CustomerBalances::debtsWithBalances($customerId)->filter(function ($debt) {
            return $debt->balance > 0.01;
        })->map(function ($debt) {
            $debt->age = max(0, Carbon::parse(CustomerBalances::debtDay($debt))->diffInDays(today(), false));
            $debt->day = CustomerBalances::debtDay($debt);

            return $debt;
        })->sortBy('day')->values();

        return view('debt_statement.show', array_merge($data, [
            'customer' => ['id' => $customerId, 'name' => $customers[$customerId]],
            'opening' => round($opening, 2),
            'lines' => $lines,
            'closing' => $balance,
            'open' => $open,
            'owedToday' => round($open->sum('balance'), 2),
        ]));
    }
}