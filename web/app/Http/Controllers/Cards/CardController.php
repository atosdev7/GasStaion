<?php

namespace App\Http\Controllers\Cards;

use App\Http\Controllers\Controller;
use App\Models\Cards\Card;
use App\Support\Admin\Audit;
use App\Support\Cards\CardPin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// The RFID cards: the cashier registers a card on the Smart210 (it arrives by sync); here the manager and the accountant see the cards with their
// balance, unlock a card locked by 5 wrong PINs, block a lost card (and unblock it), reset a PIN and change who holds the card: the name, a phone
// number and a comment. A top-up is on the Top Ups page.
class CardController extends Controller
{
    public function index(Request $request)
    {
        // every filter sits in the head of its column: a part of the name or card id, the fuel type, the status
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'fuel_type' => 'nullable|string|max:4',
            'status' => ['nullable', Rule::in(Card::STATUSES)],
        ]);

        $query = Card::query()->orderBy('holder_name')->orderBy('uid');
        if (isset($filters['q']) && $filters['q'] !== '') {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('holder_name', 'like', $like)->orWhere('holder_phone', 'like', $like)->orWhere('uid', 'like', $like);
            });
        }
        if (!empty($filters['fuel_type'])) {
            $query->where('fuel_type', $filters['fuel_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('card.index', [
            'cards' => $query->paginate(50)->appends($request->query()),
            'filters' => $filters,
            'balances' => Card::balances(),
            // the cards that hold less than nothing (a fill was poured after the card ran out): the warning list above the table
            'overdrawn' => Card::whereIn('id', collect(Card::balances())->filter(function ($kg) {
                return round((float) $kg, 2) < 0;
            })->keys())->orderBy('holder_name')->get(),
            'fuels' => config('fuel_types'),
        ]);
    }

    // The ledger of one card: every top-up and fill (and the reversal of a deleted one) with the running balance after it, newest first. Beside the
    // balance of the ledger it shows what the records that still exist add up to, so that a difference is seen.
    public function history(Request $request, $id)
    {
        $card = Card::withTrashed()->findOrFail($id);
        $filters = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d']);

        $query = $card->transactions()->orderByDesc('occurred_at')->orderByDesc('seq');
        if (!empty($filters['from'])) {
            $query->where('occurred_at', '>=', $filters['from'] . ' 00:00:00');
        }
        if (!empty($filters['to'])) {
            $query->where('occurred_at', '<=', $filters['to'] . ' 23:59:59');
        }

        return view('card.history', [
            'card' => $card,
            'entries' => $query->paginate(100)->appends($request->query()),
            'filters' => $filters,
            'balance' => $card->balance(),
            'recordsBalance' => $card->balanceFromRecords(),
            'users' => \App\Models\Admin\User::withTrashed()->pluck('name', 'id'),
            'fuels' => config('fuel_types'),
        ]);
    }

    public function edit($id)
    {
        return view('card.edit', ['card' => Card::findOrFail($id), 'fuels' => config('fuel_types')]);
    }

    // The name of the holder (it goes down to the Smart210 with the next pull), the holder's phone number and a comment. The card id, the fuel
    // type, the PIN and the status are not changed here.
    public function update(Request $request, $id)
    {
        $card = Card::findOrFail($id);
        $data = $request->validate([
            'holder_name' => 'required|string|max:100',
            'holder_phone' => 'nullable|string|max:30',
            'comment' => 'nullable|string|max:255',
        ]);

        $before = '#' . $card->id . ' ' . $card->uid . ' ' . $card->holder_name . ' / ' . $card->holder_phone;
        $card->forceFill($data)->save();
        Audit::log('card_update', $before . ' -> ' . $data['holder_name'] . ' / ' . ($data['holder_phone'] ?? ''));

        return redirect()->route('cards.index')->with('status', __('cards.updated'));
    }

    // A card locked by wrong PINs is active again, with the tries counted from zero.
    public function unlock($id)
    {
        $card = Card::findOrFail($id);
        if ($card->status !== 'locked') {
            throw ValidationException::withMessages(['card' => __('cards.not_locked')]);
        }

        $card->forceFill(['status' => 'active', 'failed_attempts' => 0])->save();
        Audit::log('card_unlock', '#' . $card->id . ' ' . $card->uid);

        return redirect()->route('cards.index')->with('status', __('cards.unlocked'));
    }

    // A lost card: it cannot be used (or topped up) until it is unblocked.
    public function block($id)
    {
        $card = Card::findOrFail($id);
        if ($card->status === 'blocked') {
            throw ValidationException::withMessages(['card' => __('cards.already_blocked')]);
        }

        $card->forceFill(['status' => 'blocked'])->save();
        Audit::log('card_block', '#' . $card->id . ' ' . $card->uid);

        return redirect()->route('cards.index')->with('status', __('cards.blocked'));
    }

    public function unblock($id)
    {
        $card = Card::findOrFail($id);
        if ($card->status !== 'blocked') {
            throw ValidationException::withMessages(['card' => __('cards.not_blocked')]);
        }

        $card->forceFill(['status' => 'active', 'failed_attempts' => 0])->save();
        Audit::log('card_unblock', '#' . $card->id . ' ' . $card->uid);

        return redirect()->route('cards.index')->with('status', __('cards.unblocked'));
    }

    public function pinForm($id)
    {
        return view('card.pin', ['card' => Card::findOrFail($id), 'fuels' => config('fuel_types')]);
    }

    // A new PIN. The tries are counted from zero again and a card locked by wrong PINs is active; a blocked (lost) card stays blocked.
    public function pinSave(Request $request, $id)
    {
        $card = Card::findOrFail($id);
        $data = $request->validate(['pin' => 'required|digits_between:4,8']);

        $card->forceFill(CardPin::make($data['pin']) + [
            'failed_attempts' => 0,
            'status' => $card->status === 'locked' ? 'active' : $card->status,
        ])->save();
        Audit::log('card_pin_reset', '#' . $card->id . ' ' . $card->uid);

        return redirect()->route('cards.index')->with('status', __('cards.pin_saved'));
    }
}
