<?php

namespace App\Http\Controllers\Confirmations;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Supplement;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Takes the confirmation of one sale or one supplement back (a manager), so that it can be edited and confirmed again. The confirmed day is open
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
