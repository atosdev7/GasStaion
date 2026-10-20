<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Sales\Sale;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Support\Dashboard\DashboardData;
use App\Support\Dashboard\TankLevels;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    // A tank at or below this share of its capacity is flagged as low.
    const LOW_TANK_PERCENT = 20;

    public function index()
    {
        if (Gate::denies('dashboard.view')) {
            return view('home', ['dashboard' => false]);
        }

        $today = Sale::where('sale_date', today()->toDateString());
        $debtIds = Debt::pluck('id');
        $tanks = TankLevels::all();

        return view('home', [
            'dashboard' => true,
            'todayKg' => (clone $today)->sum('amount_kg'),
            'todayTotal' => (clone $today)->sum('total_amount'),
            'todaySales' => (clone $today)->whereNull('reverses_sale_id')->count(),
            'openDebt' => Debt::sum('amount') - DebtPayment::whereIn('debt_id', $debtIds)->sum('amount'),
            'openLoanKg' => TicketLoan::sum('amount_kg') - TicketLoan::sum('settled_amount_kg'),
            'ticketsReady' => Ticket::where('status', config('ticket_statuses.publish'))->count(),
            'tanks' => $tanks,
            'compare' => DashboardData::compare(),
            'salesByDay' => DashboardData::salesByDay(),
            'tankTrend' => DashboardData::tankTrend($tanks),
            'ticketStock' => DashboardData::ticketStock(),
            'lowPercent' => self::LOW_TANK_PERCENT,
            'fuels' => config('fuel_types'),
        ]);
    }
}
