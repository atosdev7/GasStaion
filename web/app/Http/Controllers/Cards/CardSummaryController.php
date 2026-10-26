<?php

namespace App\Http\Controllers\Cards;

use App\Http\Controllers\Controller;
use App\Models\Cards\Card;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Support\Reports\ReportSheets;
use App\Support\Reports\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Http\Request;

// Summary of the cards by card for a period (a start date and an end date): what the card held at the start, what was topped up (kg and money),
// what was used (kg supplied with it) and what it holds at the end.
class CardSummaryController extends Controller
{
    public function index(Request $request)
    {
        return view('card.summary', $this->data($request));
    }

    public function export(Request $request)
    {
        $data = $this->data($request);

        $path = XlsxWriter::build(ReportSheets::cardSummary($data));

        return response()->download($path, 'cards-summary-' . $data['from'] . '_' . $data['to'] . '.xlsx', ['Content-Type' => XlsxWriter::MIME])->deleteFileAfterSend(true);
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

        $figures = [];
        $add = function ($card, $key, $kg) use (&$figures) {
            if (!isset($figures[$card])) {
                $figures[$card] = ['opening' => 0.0, 'topped_kg' => 0.0, 'topped_amount' => 0.0, 'used_kg' => 0.0];
            }
            $figures[$card][$key] += (float) $kg;
        };

        foreach (Sale::where('sale_type', 'card')->whereNotNull('card_id')->get() as $sale) {
            $day = substr((string) $sale->sale_date, 0, 10);
            if ($day < $from) {
                $add($sale->card_id, 'opening', $sale->amount_kg);
            } elseif ($day <= $to) {
                $add($sale->card_id, 'topped_kg', $sale->amount_kg);
                $add($sale->card_id, 'topped_amount', $sale->total_amount);
            }
        }
        foreach (Supplement::whereNotNull('card_id')->get() as $supplement) {
            $day = substr((string) ($supplement->supply_date ?: $supplement->supplied_at), 0, 10);
            if ($day < $from) {
                $add($supplement->card_id, 'opening', -$supplement->out_amount_kg);
            } elseif ($day <= $to) {
                $add($supplement->card_id, 'used_kg', $supplement->out_amount_kg);
            }
        }

        // the tickets made for the cards (the card paid for them when they were made; a restricted one gave the kg back)
        foreach (\App\Models\Tickets\Ticket::whereNotNull('card_id')->where('status', '<>', config('ticket_statuses.restrict'))->get() as $ticket) {
            $day = substr((string) $ticket->sale_date, 0, 10);
            if ($day < $from) {
                $add($ticket->card_id, 'opening', -$ticket->amount_kg);
            } elseif ($day <= $to) {
                $add($ticket->card_id, 'used_kg', $ticket->amount_kg);
            }
        }

        // a card with nothing in the period and nothing on it is left out
        $cards = Card::withTrashed()->orderBy('holder_name')->orderBy('uid')->get();
        $rows = [];
        foreach ($cards as $card) {
            if (!isset($figures[$card->id])) {
                continue;
            }
            $row = $figures[$card->id];
            $row['closing'] = $row['opening'] + $row['topped_kg'] - $row['used_kg'];
            if (max(abs($row['opening']), abs($row['topped_kg']), abs($row['used_kg']), abs($row['closing'])) < 0.005) {
                continue;
            }
            $rows[] = ['card' => $card] + $row;
        }

        return ['from' => $from, 'to' => $to, 'rows' => $rows, 'fuels' => config('fuel_types')];
    }
}
