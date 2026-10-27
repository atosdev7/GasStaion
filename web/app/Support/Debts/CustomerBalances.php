<?php

namespace App\Support\Debts;

use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Debts\TicketLoan;

// What a customer still owes: the debts with a balance (money) and the ticket loans with a balance (kg). A customer who owes something is not deleted.
class CustomerBalances
{
    // The debts of a customer that are still open: [debt, balance] (the balance is the debt less its returns and the payments tied to it).
    public static function openDebts($customerId)
    {
        $debts = Debt::with('adjustments')->where('customer_id', $customerId)->get();
        $paid = DebtPayment::where('customer_id', $customerId)->whereNotNull('debt_id')->selectRaw('debt_id, SUM(amount) AS paid')->groupBy('debt_id')->pluck('paid', 'debt_id');

        return $debts->map(function ($debt) use ($paid) {
            $debt->balance = round($debt->net_amount - (float) ($paid[$debt->id] ?? 0), 2);

            return $debt;
        })->filter(function ($debt) {
            return $debt->balance > 0.01;
        })->values();
    }

    // The debts (of one customer, or of all) with what is settled and what is left: `settled` is what the payments tied to the debt add up to, `balance` the debt
    // less its returns and that. The payments that are not tied to a debt are not in it (they are the customer's credit).
    public static function debtsWithBalances($customerId = null)
    {
        $payments = DebtPayment::whereNotNull('debt_id')->when($customerId, function ($q) use ($customerId) {
            $q->where('customer_id', $customerId);
        })->selectRaw('debt_id, SUM(amount) AS paid')->groupBy('debt_id')->pluck('paid', 'debt_id');

        return Debt::with(['sale', 'adjustments'])->when($customerId, function ($q) use ($customerId) {
            $q->where('customer_id', $customerId);
        })->get()->map(function ($debt) use ($payments) {
            $debt->settled = round((float) ($payments[$debt->id] ?? 0), 2);
            $debt->balance = round($debt->net_amount - $debt->settled, 2);

            return $debt;
        });
    }

    // The day a debt is counted from: the day of its sale, or for an opening balance the day it was entered with ("as of"), else the day it was typed in (Y-m-d).
    public static function debtDay(Debt $debt)
    {
        return substr((string) ($debt->display_date ?: ($debt->debt_date ?: $debt->created_at)), 0, 10);
    }

    // The ticket loans of a customer that are not settled yet (kg).
    public static function openLoans($customerId)
    {
        return TicketLoan::where('customer_id', $customerId)->whereRaw('amount_kg - settled_amount_kg > 0.005')->get();
    }

    // null when the customer owes nothing, otherwise the sentence that says what is owed.
    public static function blocker($customerId)
    {
        $debts = self::openDebts($customerId);
        $loans = self::openLoans($customerId);
        if ($debts->isEmpty() && $loans->isEmpty()) {
            return null;
        }

        return __('sales.customer_owes', [
            'debts' => $debts->count(), 'money' => number_format($debts->sum('balance'), 2),
            'loans' => $loans->count(), 'kg' => number_format($loans->sum(function ($loan) { return $loan->amount_kg - $loan->settled_amount_kg; }), 2),
        ]);
    }
}