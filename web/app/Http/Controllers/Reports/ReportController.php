<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Fuel\Nozzle;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Support\Reports\ReportSheets;
use App\Support\Dashboard\TankLevels;
use App\Support\Reports\XlsxWriter;
use App\Support\Tickets\TicketStock;
use Carbon\Carbon;
use Illuminate\Http\Request;

// Read-only reports. Everything goes through Eloquent so soft-deleted rows stay out of the sums.
class ReportController extends Controller
{
    const REPORTS = ['sales', 'cash', 'profit', 'customer', 'tickets', 'tanks', 'supply'];

    public function index()
    {
        return view('report.index', ['reports' => self::REPORTS]);
    }

    public function sales(Request $request)
    {
        list($from, $to) = $this->period($request);
        // Return entries net the sums out but are not sales of their own, so only originals are counted.
        $sums = 'SUM(CASE WHEN reverses_sale_id IS NULL THEN 1 ELSE 0 END) AS sales, SUM(amount_kg) AS kg, SUM(total_amount) AS total, SUM(paid_amount) AS paid, SUM(unpaid_amount) AS unpaid';

        return $this->respond($request, 'sales', [
            'from' => $from, 'to' => $to,
            'summary' => $this->salesIn($from, $to)
                ->selectRaw("fuel_type, sale_type, `usage`, payment_method, $sums")
                ->groupBy('fuel_type', 'sale_type', 'usage', 'payment_method')
                ->orderBy('fuel_type')->orderBy('sale_type')->get(),
            'daily' => $this->salesIn($from, $to)
                ->selectRaw("sale_date AS day, $sums")
                ->groupBy('day')->orderBy('day')->get(),
            'fuels' => config('fuel_types'),
        ]);
    }

    public function cash(Request $request)
    {
        list($from, $to) = $this->period($request);

        $fromSales = $this->salesIn($from, $to)->where('paid_amount', '<>', 0)
            ->selectRaw('sale_date AS day, payment_method, SUM(paid_amount) AS amount')
            ->groupBy('day', 'payment_method')->get();
        $fromPayments = DebtPayment::whereBetween('paid_date', [$from, $to])
            ->selectRaw('paid_date AS day, payment_method, SUM(amount) AS amount')
            ->groupBy('day', 'payment_method')->get();

        $rows = [];
        foreach (['sales' => $fromSales, 'payments' => $fromPayments] as $source => $list) {
            foreach ($list as $r) {
                $key = $r->day . '|' . $r->payment_method;
                $rows[$key] = $rows[$key] ?? (object) ['day' => $r->day, 'method' => $r->payment_method, 'sales' => 0, 'payments' => 0];
                $rows[$key]->$source += (float) $r->amount;
            }
        }
        ksort($rows);

        return $this->respond($request, 'cash', ['from' => $from, 'to' => $to, 'rows' => collect(array_values($rows))]);
    }

    public function profit(Request $request)
    {
        list($from, $to) = $this->period($request);

        $rows = $this->salesIn($from, $to)
            ->selectRaw('fuel_type, `usage`, SUM(amount_kg) AS kg, SUM(total_amount) AS revenue,'
                . ' SUM(CASE WHEN cost IS NULL THEN 0 ELSE cost * amount_kg END) AS cost_total,'
                . ' SUM(CASE WHEN cost IS NULL AND reverses_sale_id IS NULL THEN 1 ELSE 0 END) AS missing_cost')
            ->groupBy('fuel_type', 'usage')->orderBy('fuel_type')->get()
            ->each(function ($r) {
                $r->profit = $r->revenue - $r->cost_total;
            });

        return $this->respond($request, 'profit', ['from' => $from, 'to' => $to, 'rows' => $rows, 'fuels' => config('fuel_types')]);
    }

    public function customer(Request $request)
    {
        list($from, $to) = $this->period($request);
        $customers = Customer::orderBy('name')->pluck('name', 'id');
        $customerId = $request->query('customer_id');
        $data = ['from' => $from, 'to' => $to, 'customers' => $customers, 'customerId' => $customerId, 'fuels' => config('fuel_types')];

        if (!$customers->has($customerId)) {
            return view('report.customer', $data + ['customer' => null]);
        }

        $debts = Debt::with(['sale', 'adjustments'])->where('customer_id', $customerId)->orderBy('created_at')->get();
        $paidPerDebt = DebtPayment::whereIn('debt_id', $debts->pluck('id'))
            ->selectRaw('debt_id, SUM(amount) AS paid')->groupBy('debt_id')->pluck('paid', 'debt_id');
        $debts->each(function ($d) use ($paidPerDebt) {
            $d->settled = (float) ($paidPerDebt[$d->id] ?? 0);
            $d->balance = $d->net_amount - $d->settled;
        });

        $loans = TicketLoan::where('customer_id', $customerId)->orderBy('loaned_at')->get()->each(function ($l) {
            $l->balance_kg = $l->amount_kg - $l->settled_amount_kg;
        });

        return $this->respond($request, 'customer', $data + [
            'customer' => $customers[$customerId],
            'sales' => $this->salesIn($from, $to)->where('customer_id', $customerId)->orderBy('sale_date')->orderBy('created_at')->get(),
            'payments' => DebtPayment::where('customer_id', $customerId)->whereBetween('paid_date', [$from, $to])->orderBy('paid_date')->get(),
            'debts' => $debts,
            'unallocated' => (float) DebtPayment::where('customer_id', $customerId)->whereNull('debt_id')->sum('amount'),
            'loans' => $loans,
        ]);
    }

    public function tickets(Request $request)
    {
        $rows = [];
        $row = function ($fuelType, $spec) use (&$rows) {
            $key = $fuelType . ':' . ($spec === null ? 'direct' : $spec);

            return $rows[$key] = $rows[$key] ?? (object) [
                'fuel_type' => $fuelType, 'spec' => $spec, 'received' => null, 'blank' => null,
                'publish' => 0, 'sale' => 0, 'supply' => 0, 'restrict' => 0,
            ];
        };

        foreach (TicketStock::summary() as $s) {
            $r = $row($s->fuel_type, $s->spec);
            $r->received = $s->received;
            $r->blank = $s->available;
        }

        $statuses = array_flip(config('ticket_statuses'));
        $counts = Ticket::selectRaw('fuel_type, spec, status, COUNT(*) AS n')->groupBy('fuel_type', 'spec', 'status')->get();
        foreach ($counts as $c) {
            $row($c->fuel_type, $c->spec === null ? null : (int) $c->spec)->{$statuses[$c->status]} = (int) $c->n;
        }
        ksort($rows);

        return $this->respond($request, 'tickets', ['rows' => collect(array_values($rows)), 'fuels' => config('fuel_types')]);
    }

    public function tanks(Request $request)
    {
        return $this->respond($request, 'tanks', ['rows' => TankLevels::all(), 'fuels' => config('fuel_types')]);
    }

    public function supply(Request $request)
    {
        list($from, $to) = $this->period($request);

        return $this->respond($request, 'supply', [
            'from' => $from, 'to' => $to,
            'rows' => Supplement::whereRaw(self::day('supplied_at', 'supply_date') . ' BETWEEN ? AND ?', [$from, $to])
                ->selectRaw(self::day('supplied_at', 'supply_date') . ' AS day, nozzle_id, COUNT(*) AS supplies, SUM(out_amount_lt) AS liters, SUM(out_amount_kg) AS kg')
                ->groupBy('day', 'nozzle_id')->orderBy('day')->get(),
            'nozzleLabels' => Nozzle::labels(),
        ]);
    }

    // The page, or the same data as an .xlsx file when the request has ?export=xls.
    private function respond(Request $request, $name, array $data)
    {
        if ($request->query('export') !== 'xls') {
            return view("report.$name", $data);
        }

        $path = XlsxWriter::build(call_user_func([ReportSheets::class, $name], $data));
        $file = 'report-' . $name . '-' . (isset($data['from']) ? $data['from'] . '_' . $data['to'] : today()->toDateString()) . '.xlsx';

        return response()->download($path, $file, ['Content-Type' => XlsxWriter::MIME])->deleteFileAfterSend(true);
    }

    private function salesIn($from, $to)
    {
        return Sale::whereBetween('sale_date', [$from, $to]);
    }

    // The day a record belongs to is its date field (the 9:00 business day of the station). A record the terminal uploaded before its
    // checkout has no date yet and counts under the day of its time stamp, so it is not missing from the report.
    public static function day($timeColumn, $dateColumn = 'date')
    {
        return "COALESCE(`$dateColumn`, DATE($timeColumn))";
    }

    // Defaults to the current month so far.
    private function period(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        return [
            Carbon::parse($data['from'] ?? today()->startOfMonth())->toDateString(),
            Carbon::parse($data['to'] ?? today())->toDateString(),
        ];
    }
}
