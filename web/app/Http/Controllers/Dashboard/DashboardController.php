<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtAdjustment;
use App\Models\Debts\DebtPayment;
use App\Models\Sales\Sale;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Support\Dashboard\DashboardData;
use App\Support\Dashboard\TankLevels;
use App\Support\Reports\StockOrdering;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    // A tank at or below this share of its capacity is flagged as low.
    const LOW_TANK_PERCENT = 20;

    // The free fuel of a fuel type that lasts fewer days than this at the pace of the last 30 days is flagged.
    const LOW_COVER_DAYS = 7;

    public function index()
    {
        if (Gate::denies('dashboard.view')) {
            return view('home', ['dashboard' => false]);
        }

        // sales of fuel only: self use, given away and fuel on ticket loan are not sales (the same count as the reports)
        $today = Sale::where('sale_date', today()->toDateString())->where('usage', 'sale');
        $tanks = TankLevels::all();

        return view('home', [
            'dashboard' => true,
            'todayKg' => (clone $today)->sum('amount_kg'),
            'todayTotal' => (clone $today)->sum('total_amount'),
            'todaySales' => (clone $today)->whereNull('reverses_sale_id')->count(),
            // what is owed: the debts and their adjustments less every payment (also those not tied to a debt), as the Debts Summary counts it
            'openDebt' => Debt::sum('amount') + DebtAdjustment::whereIn('debt_id', Debt::select('id'))->sum('amount') - DebtPayment::sum('amount'),
            'openLoanKg' => TicketLoan::sum('amount_kg') - TicketLoan::sum('settled_amount_kg'),
            'ticketsReady' => Ticket::where('status', config('ticket_statuses.publish'))->count(),
            'tanks' => $tanks,
            'compare' => DashboardData::compare(),
            'salesByDay' => DashboardData::salesByDay(),
            'tankTrend' => DashboardData::tankTrend($tanks),
            'ticketStock' => DashboardData::ticketStock(),
            'lowPercent' => self::LOW_TANK_PERCENT,
            'ordering' => StockOrdering::rows(),
            'lowCoverDays' => self::LOW_COVER_DAYS,
            'fuels' => config('fuel_types'),
        ]);
    }
}
