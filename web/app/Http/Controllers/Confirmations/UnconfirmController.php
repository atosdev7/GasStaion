<?php

namespace App\Http\Controllers\Confirmations;

use App\Http\Controllers\Controller;
use App\Models\Debts\DebtPayment;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelOutbound;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\TicketInbound;
use App\Models\Tickets\TicketPublish;
use App\Support\Admin\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Takes the confirmation of one sale, supplement, ticket inbound, ticket publish, debt payment, fuel inbound, hand-made fuel outbound or ticket settlement back (a manager), so that it can be edited and confirmed again. The confirmed day is open
// again for that record: the daily confirmation shows it as not confirmed.
class UnconfirmController extends Controller
{
    public function sale(Request $request, $id)
    {
        return $this->unconfirm($request, Sale::findOrFail($id), 'sale');
    }

    public function supplement(Request $request, $id)
    {
        return $this->unconfirm($request, Supplement::findOrFail($id), 'supplement');
    }

    public function ticketInbound(Request $request, $id)
    {
        return $this->unconfirm($request, TicketInbound::findOrFail($id), 'ticket inbound');
    }

    public function ticketPublish(Request $request, $id)
    {
        return $this->unconfirm($request, TicketPublish::findOrFail($id), 'ticket publish');
    }

    public function debtPayment(Request $request, $id)
    {
        return $this->unconfirm($request, DebtPayment::findOrFail($id), 'debt payment');
    }

    public function fuelInbound(Request $request, $id)
    {
        return $this->unconfirm($request, FuelInbound::findOrFail($id), 'fuel inbound');
    }

    public function fuelOutbound(Request $request, $id)
    {
        return $this->unconfirm($request, FuelOutbound::findOrFail($id), 'fuel outbound');
    }

    public function ticketSettlement(Request $request, $id)
    {
        return $this->unconfirm($request, TicketLoanSettlement::findOrFail($id), 'ticket settlement');
    }

    private function unconfirm(Request $request, $record, $kind)
    {
        if (!$record->confirmed_at) {
            throw ValidationException::withMessages(['record' => __('confirm.not_confirmed')]);
        }

        $record->forceFill(['confirmed_at' => null, 'confirmed_by' => null])->save();
        Audit::log('unconfirm', $kind . ' #' . $record->id);

        $back = (string) url()->previous();

        return redirect(Str::startsWith($back, rtrim(url('/'), '/') . '/') ? $back : route('confirmations.index'))->with('status', __('confirm.unconfirmed'));
    }
}
