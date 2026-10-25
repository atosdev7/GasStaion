<?php

namespace App\Support\Sales;

use App\Models\Sales\Sale;

// Who may see and change which sales.
//
// Kind of sale: a ticket sale needs ticket_sales.view / .manage, a spot sale spot_sales.view / .manage, a card sale (a top-up) card_sales.view (changing
// one needs card_sales.edit / .delete as before). Whose sale: admin, manager and accountant have sales.all and work on every sale; whoever manages the
// ticket sales (the assistant) works on every ticket sale; the others (the cashier, who only looks) see only the sales they issued themselves (`user_id`).
class SaleAccess
{
    private static function kind($saleType)
    {
        $kinds = ['ticket' => 'ticket_sales', 'spot' => 'spot_sales', 'card' => 'card_sales'];

        return isset($kinds[$saleType]) ? $kinds[$saleType] : 'ticket_sales';
    }

    // Does he see every sale of this kind, or only the ones he issued?
    public static function seesAll($user, $saleType)
    {
        return $user && ($user->can('sales.all') || ($saleType === 'ticket' && $user->can('ticket_sales.manage')));
    }

    // The sales of a kind that a user may look at: all of them, or the ones he issued.
    public static function scope($query, $user, $saleType)
    {
        return self::seesAll($user, $saleType) ? $query : $query->where('user_id', $user->id);
    }

    public static function canSee($user, Sale $sale)
    {
        return $user
            && $user->can(self::kind($sale->sale_type) . '.view')
            && (self::seesAll($user, $sale->sale_type) || (int) $sale->user_id === (int) $user->id);
    }

    // The earliest day he may date a sale (entering it or changing its date): config('sales.backdate_days') days back; null (no limit) for whoever has
    // sales.backdate.
    public static function earliestDate($user)
    {
        return $user && $user->can('sales.backdate') ? null : today()->subDays(config('sales.backdate_days'))->toDateString();
    }

    // The rules of the sale_date field of a form.
    public static function dateRules($user, array $first)
    {
        $earliest = self::earliestDate($user);

        return array_merge($first, ['date_format:Y-m-d', 'before_or_equal:today'], $earliest ? ['after_or_equal:' . $earliest] : []);
    }

    public static function dateMessages()
    {
        return ['sale_date.after_or_equal' => __('sales.sale_date_too_old', ['days' => config('sales.backdate_days')])];
    }

    // May he add, edit, delete or return this sale (the permission of the action itself, sales.delete and so on, is checked by the route)?
    public static function canChange($user, Sale $sale)
    {
        if (!$user || !self::canSee($user, $sale)) {
            return false;
        }

        return $sale->sale_type === 'card' ? true : $user->can(self::kind($sale->sale_type) . '.manage');
    }

    public static function authorizeChange($user, Sale $sale)
    {
        abort_unless(self::canChange($user, $sale), 403);
    }
}
