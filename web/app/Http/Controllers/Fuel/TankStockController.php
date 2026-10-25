<?php

namespace App\Http\Controllers\Fuel;

use App\Http\Controllers\Controller;
use App\Support\Fuel\TankStockSummary;

// The stock of the tanks by fuel type and its cost, as of now. Read-only; the numbers come from TankStockSummary.
class TankStockController extends Controller
{
    public function index()
    {
        return view('tank_stock.index', TankStockSummary::build() + ['names' => config('fuel_types'), 'asOf' => now()->format('Y-m-d H:i')]);
    }
}
