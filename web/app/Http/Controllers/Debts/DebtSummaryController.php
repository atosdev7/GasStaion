<?php

namespace App\Http\Controllers\Debts;

use App\Http\Controllers\Controller;
use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Support\Reports\ReportSheets;
use App\Support\Reports\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Http\Request;

// Summary of Debts Management by customer for a period (a start date and an end date): for the money owed (the Payments tab, bank and cash) and for the tickets
// borrowed (the Ticket Loans tab, kg by fuel type) what was owed at the start, what was added, what was paid / settled and what is owed at the end.
class DebtSummaryController extends Controller
{
    public function index(Request $request)
    {
        return view('debt_summary.index', $this->data($request));
    }

    // The two tabs as an .xlsx file: a sheet each (the loans only for people who may see them).
    public function export(Request $request)
    {
        $data = $this->data($request);

        $path = XlsxWriter::build(ReportSheets::debtSummary($data));

        return response()->download($path, 'debts-summary-' . $data['from'] . '_' . $data['to'] . '.xlsx', ['Content-Type' => XlsxWriter::MIME])->deleteFileAfterSend(true);
    }

    private function data(Request $request)
    {
        $input = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);
        // by default the month so far
        $from = Carbon::parse($input['from'] ?? today()->startOfMonth())->toDateString();
        $to = Carbon::parse($input['to'] ?? today())->toDateString();

        $names = Customer::withTrashed()->pluck('name', 'id');
        $showLoans = $request->user()->can('loans.view');

        return [
            'from' => $from,
            'to' => $to,
            'payments' => $this->payments($from, $to, $names),
            'loans' => $showLoans ? $this->loans($from, $to, $names) : null,
            'fuels' => config('fuel_types'),
            'fuelKeys' => array_keys(config('fuel_types')),
        ];
    }

    const METHODS = ['bank', 'cash'];

    // Per customer, each in bank and cash (by the payment method of the debt, and of the payment): owed at the start (the debts before the start
    // date and the opening balances, less what was paid before it), the debts of the period (by the day of the sale), the payments of the period
    // (all of them, also those not tied to one debt) and owed at the end. A figure is [bank => n, cash => n]; a payment made in the other method
    // than its debt was can leave a method below zero.
    private function payments($from, $to, $names)
    {
        $zero = ['bank' => 0.0, 'cash' => 0.0];
        $rows = [];
        $add = function ($customer, $key, $method, $amount) use (&$rows, $zero) {
            if (!isset($rows[$customer])) {
                $rows[$customer] = ['opening' => $zero, 'debts' => $zero, 'payments' => $zero];
            }
            $rows[$customer][$key][$method] += (float) $amount;
        };

        foreach (Debt::with('sale')->get() as $debt) {
            // an opening balance entered by hand has no sale and no day: it belongs to the start
            $day = $debt->sale_id === null ? null : substr((string) $debt->display_date, 0, 10);
            if ($day === null || $day < $from) {
                $add($debt->customer_id, 'opening', $debt->payment_method, $debt->amount);
            } elseif ($day <= $to) {
                $add($debt->customer_id, 'debts', $debt->payment_method, $debt->amount);
            }
        }
        foreach (DebtPayment::get() as $payment) {
            $day = substr((string) $payment->paid_date, 0, 10);
            if ($day < $from) {
                $add($payment->customer_id, 'opening', $payment->payment_method, -$payment->amount);
            } elseif ($day <= $to) {
                $add($payment->customer_id, 'payments', $payment->payment_method, $payment->amount);
            }
        }

        $out = [];
        foreach ($rows as $customer => $row) {
            $row['closing'] = [];
            $largest = 0.0;
            foreach (self::METHODS as $method) {
                $row['closing'][$method] = $row['opening'][$method] + $row['debts'][$method] - $row['payments'][$method];
                foreach (['opening', 'debts', 'payments', 'closing'] as $key) {
                    $largest = max($largest, abs($row[$key][$method]));
                }
            }
            if ($largest < 0.005) {
                continue;
            }
            $out[] = ['customer_id' => $customer, 'name' => isset($names[$customer]) ? $names[$customer] : (string) $customer] + $row;
        }
        usort($out, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }

    // The same for the tickets borrowed, in kg and by fuel type: [customer, name, fuels => [fuel => opening, loaned, settled, closing]].
    private function loans($from, $to, $names)
    {
        $rows = [];
        $add = function ($customer, $fuel, $key, $kg) use (&$rows) {
            if (!isset($rows[$customer][$fuel])) {
                $rows[$customer][$fuel] = ['opening' => 0.0, 'loaned' => 0.0, 'settled' => 0.0];
            }
            $rows[$customer][$fuel][$key] += (float) $kg;
        };

        foreach (TicketLoan::get() as $loan) {
            $day = substr((string) ($loan->date ?: $loan->loaned_at), 0, 10);
            if ($day < $from) {
                $add($loan->customer_id, $loan->fuel_type, 'opening', $loan->amount_kg);
            } elseif ($day <= $to) {
                $add($loan->customer_id, $loan->fuel_type, 'loaned', $loan->amount_kg);
            }
        }
        $settlements = TicketLoanSettlement::with(['loan' => function ($query) {
            $query->withTrashed();
        }])->get();
        foreach ($settlements as $settlement) {
            if (!$settlement->loan) {
                continue;
            }
            $day = substr((string) ($settlement->date ?: $settlement->settled_at), 0, 10);
            if ($day < $from) {
                $add($settlement->loan->customer_id, $settlement->loan->fuel_type, 'opening', -$settlement->amount_kg);
            } elseif ($day <= $to) {
                $add($settlement->loan->customer_id, $settlement->loan->fuel_type, 'settled', $settlement->amount_kg);
            }
        }

        $out = [];
        foreach ($rows as $customer => $byFuel) {
            $keep = false;
            foreach ($byFuel as $fuel => $row) {
                $byFuel[$fuel]['closing'] = $row['opening'] + $row['loaned'] - $row['settled'];
                if (max(abs($row['opening']), abs($row['loaned']), abs($row['settled']), abs($byFuel[$fuel]['closing'])) >= 0.005) {
                    $keep = true;
                }
            }
            if ($keep) {
                $out[] = ['customer_id' => $customer, 'name' => isset($names[$customer]) ? $names[$customer] : (string) $customer, 'fuels' => $byFuel];
            }
        }
        usort($out, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }
}
