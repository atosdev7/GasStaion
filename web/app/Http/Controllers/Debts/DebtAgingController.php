<?php

namespace App\Http\Controllers\Debts;

use App\Http\Controllers\Controller;
use App\Models\Debts\DebtPayment;
use App\Models\Sales\Customer;
use App\Support\Debts\CustomerBalances;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// How old what the customers owe is: the open debts of each customer by the days since the debt was made (the day of the sale, or of the entry for an opening balance),
// in the buckets of BUCKETS, today. The payments that are not tied to a debt are the customer's credit and are shown apart: they offset nothing here.
class DebtAgingController extends Controller
{
    // [first day, last day] of each bucket; the last one has no end
    const BUCKETS = [[0, 7], [8, 30], [31, 60], [61, null]];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'customer' => 'nullable|integer',
            'payment_method' => ['nullable', Rule::in(DebtController::PAYMENT_METHODS)],
        ]);

        $today = today();
        $rows = [];
        foreach (CustomerBalances::debtsWithBalances(!empty($filters['customer']) ? (int) $filters['customer'] : null) as $debt) {
            if ($debt->balance <= 0.01 || (!empty($filters['payment_method']) && $debt->payment_method !== $filters['payment_method'])) {
                continue;
            }
            $days = max(0, Carbon::parse(CustomerBalances::debtDay($debt))->diffInDays($today, false));
            $bucket = $this->bucketOf($days);
            if (!isset($rows[$debt->customer_id])) {
                $rows[$debt->customer_id] = ['customer_id' => $debt->customer_id, 'buckets' => array_fill(0, count(self::BUCKETS), 0.0), 'total' => 0.0, 'oldest' => 0, 'debts' => 0];
            }
            $rows[$debt->customer_id]['buckets'][$bucket] += $debt->balance;
            $rows[$debt->customer_id]['total'] += $debt->balance;
            $rows[$debt->customer_id]['oldest'] = max($rows[$debt->customer_id]['oldest'], $days);
            $rows[$debt->customer_id]['debts']++;
        }

        // what each customer paid without naming a debt
        $credit = DebtPayment::whereNull('debt_id')->selectRaw('customer_id, SUM(amount) AS credit')->groupBy('customer_id')->pluck('credit', 'customer_id');
        $names = Customer::withTrashed()->pluck('name', 'id');
        foreach ($rows as $id => &$row) {
            $row['name'] = isset($names[$id]) ? $names[$id] : (string) $id;
            $row['credit'] = round((float) ($credit[$id] ?? 0), 2);
        }
        unset($row);
        // the oldest debt first: that is the one to ask about
        usort($rows, function ($a, $b) {
            return $b['oldest'] <=> $a['oldest'] ?: strcasecmp($a['name'], $b['name']);
        });

        $totals = ['buckets' => array_fill(0, count(self::BUCKETS), 0.0), 'total' => 0.0, 'credit' => 0.0];
        foreach ($rows as $row) {
            foreach ($row['buckets'] as $i => $amount) {
                $totals['buckets'][$i] += $amount;
            }
            $totals['total'] += $row['total'];
            $totals['credit'] += $row['credit'];
        }

        return view('debt_aging.index', [
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $filters,
            'buckets' => self::BUCKETS,
            'customers' => Customer::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'today' => $today->toDateString(),
        ]);
    }

    // The index of the bucket a number of days falls in.
    public static function bucketOf($days)
    {
        foreach (self::BUCKETS as $i => $bucket) {
            if ($bucket[1] === null || $days <= $bucket[1]) {
                return $i;
            }
        }

        return count(self::BUCKETS) - 1;
    }
}