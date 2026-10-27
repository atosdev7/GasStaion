<?php

use App\Http\Middleware\SetLocale;

Route::middleware(SetLocale::class)->group(function () {
    Route::get('login', 'Auth\SessionController@showLoginForm')->name('login');
    Route::post('login', 'Auth\SessionController@login');
    Route::post('logout', 'Auth\SessionController@logout')->name('logout');

    Route::get('locale/{locale}', function ($locale) {
        if (array_key_exists($locale, config('locales'))) {
            session(['locale' => $locale]);
        }
        return back();
    })->name('locale');

    Route::middleware('auth')->group(function () {
        Route::get('/', 'Dashboard\DashboardController@index')->name('home');

        // Master data: list needs "<perm>.view" (?trashed=1 shows deleted rows), changes need "<perm>.manage".
        $crud = function ($path, $controller, $perm) {
            Route::get($path, "$controller@index")->name("$path.index")->middleware("can:$perm.view");
            Route::middleware("can:$perm.manage")->group(function () use ($path, $controller) {
                Route::get("$path/create", "$controller@create")->name("$path.create");
                Route::post($path, "$controller@store")->name("$path.store");
                Route::get("$path/{id}/edit", "$controller@edit")->name("$path.edit");
                Route::put("$path/{id}", "$controller@update")->name("$path.update");
                Route::post("$path/{id}/toggle", "$controller@toggle")->name("$path.toggle");
                Route::delete("$path/{id}", "$controller@destroy")->name("$path.destroy");
                Route::post("$path/{id}/restore", "$controller@restore")->name("$path.restore");
            });
        };

        Route::get('inbound', 'Fuel\FuelInboundController@index')->name('inbound.index')->middleware('can:inbound.view');
        Route::middleware('can:inbound.manage')->group(function () {
            Route::get('inbound/create', 'Fuel\FuelInboundController@create')->name('inbound.create');
            Route::post('inbound', 'Fuel\FuelInboundController@store')->name('inbound.store');
            Route::post('inbound/{id}/default', 'Fuel\FuelInboundController@makeDefault')->name('inbound.default');
            Route::get('inbound/{id}/edit', 'Fuel\FuelInboundController@edit')->name('inbound.edit');
            Route::put('inbound/{id}', 'Fuel\FuelInboundController@update')->name('inbound.update');
            Route::delete('inbound/{id}', 'Fuel\FuelInboundController@destroy')->name('inbound.destroy');
        });

        Route::get('outbound', 'Fuel\OutboundController@index')->name('outbound.index')->middleware('can:outbound.view');
        Route::middleware('can:outbound.manage')->group(function () {
            Route::get('outbound/create', 'Fuel\OutboundController@create')->name('outbound.create');
            Route::post('outbound', 'Fuel\OutboundController@store')->name('outbound.store');
            Route::get('outbound/{id}/edit', 'Fuel\OutboundController@edit')->name('outbound.edit');
            Route::put('outbound/{id}', 'Fuel\OutboundController@update')->name('outbound.update');
            Route::delete('outbound/{id}', 'Fuel\OutboundController@destroy')->name('outbound.destroy');
        });
        Route::get('tank-stock', 'Fuel\TankStockController@index')->name('tank-stock.index')->middleware('can:outbound.view');

        Route::get('ticket-inbound', 'Tickets\TicketInboundController@index')->name('ticket-inbound.index')->middleware('can:ticket_stock.view');
        Route::middleware('can:ticket_stock.manage')->group(function () {
            Route::get('ticket-inbound/create', 'Tickets\TicketInboundController@create')->name('ticket-inbound.create');
            Route::post('ticket-inbound', 'Tickets\TicketInboundController@store')->name('ticket-inbound.store');
            Route::get('ticket-inbound/{id}/edit', 'Tickets\TicketInboundController@edit')->name('ticket-inbound.edit');
            Route::put('ticket-inbound/{id}', 'Tickets\TicketInboundController@update')->name('ticket-inbound.update');
            Route::delete('ticket-inbound/{id}', 'Tickets\TicketInboundController@destroy')->name('ticket-inbound.destroy');
        });

        Route::get('ticket-summary', 'Tickets\TicketSummaryController@index')->name('ticket-summary.index')->middleware('can:ticket_stock.view');

        Route::get('tickets/lookup', 'Tickets\TicketLookupController@index')->name('tickets.lookup')->middleware('can:tickets.lookup');
        Route::middleware('can:tickets.restrict')->group(function () {
            Route::post('tickets/{barcode}/restrict', 'Tickets\TicketRestrictController@restrict')->name('tickets.restrict');
            Route::post('tickets/{barcode}/lift-restriction', 'Tickets\TicketRestrictController@lift')->name('tickets.lift-restriction');
        });

        // changes or deletes many tickets at once, for the go-live (the manager and the accountant, like the opening balances)
        Route::middleware('can:balances.manage')->group(function () {
            Route::get('ticket-bulk', 'Tickets\TicketBulkController@index')->name('ticket-bulk.index');
            Route::post('ticket-bulk', 'Tickets\TicketBulkController@run')->name('ticket-bulk.run');
        });

        Route::get('ticket-publish', 'Tickets\TicketPublishController@index')->name('ticket-publish.index')->middleware('can:ticket_stock.view');
        Route::get('ticket-publish/{id}', 'Tickets\TicketPublishController@show')->name('ticket-publish.show')->where('id', '[0-9]+')->middleware('can:ticket_stock.view');
        Route::middleware('can:ticket_stock.manage')->group(function () {
            Route::get('ticket-publish/create', 'Tickets\TicketPublishController@create')->name('ticket-publish.create');
            Route::post('ticket-publish', 'Tickets\TicketPublishController@store')->name('ticket-publish.store');
        });

        Route::get('ticket-sales', 'Sales\SaleController@index')->name('ticket-sales.index')->middleware('can:ticket_sales.view');
        Route::get('spot-sales', 'Sales\SaleController@indexSpot')->name('spot-sales.index')->middleware('can:spot_sales.view');
        Route::delete('ticket-sales/{id}', 'Sales\SaleController@destroy')->name('ticket-sales.destroy')->middleware('can:sales.delete');
        Route::middleware('can:sales.return')->group(function () {
            Route::get('ticket-sales/{id}/return', 'Sales\SaleReturnController@create')->name('ticket-sales.return');
            Route::post('ticket-sales/{id}/return', 'Sales\SaleReturnController@store')->name('ticket-sales.return.store');
        });
        Route::middleware('can:ticket_sales.manage')->group(function () {
            Route::get('ticket-sales/create', 'Sales\SaleController@create')->name('ticket-sales.create');
            Route::post('ticket-sales', 'Sales\SaleController@store')->name('ticket-sales.store'); // (a spot sale needs spot_sales.manage: checked in store())
        });
        Route::get('spot-sales/create', 'Sales\SaleController@createSpot')->name('spot-sales.create')->middleware('can:spot_sales.manage');
        Route::get('ticket-sales/{id}', 'Sales\SaleController@show')->name('ticket-sales.show')->middleware('can:ticket_sales.view')->where('id', '[0-9a-fA-F-]{36}');
        // the date, comment and receipt of a sale (the permission is checked for the kind of sale in the controller)
        Route::get('ticket-sales/{id}/edit', 'Sales\SaleEditController@edit')->name('ticket-sales.edit')->where('id', '[0-9a-fA-F-]{36}');
        Route::put('ticket-sales/{id}', 'Sales\SaleEditController@update')->name('ticket-sales.update')->where('id', '[0-9a-fA-F-]{36}');
        Route::middleware('can:confirmations.unconfirm')->group(function () {
            Route::post('ticket-sales/{id}/unconfirm', 'Confirmations\UnconfirmController@sale')->name('ticket-sales.unconfirm')->where('id', '[0-9a-fA-F-]{36}');
            Route::post('supplements/{id}/unconfirm', 'Confirmations\UnconfirmController@supplement')->name('supplements.unconfirm')->where('id', '[0-9a-fA-F-]{36}');
            Route::post('ticket-inbound/{id}/unconfirm', 'Confirmations\UnconfirmController@ticketInbound')->name('ticket-inbound.unconfirm')->where('id', '[0-9]+');
            Route::post('ticket-publish/{id}/unconfirm', 'Confirmations\UnconfirmController@ticketPublish')->name('ticket-publish.unconfirm')->where('id', '[0-9]+');
            Route::post('payments/{id}/unconfirm', 'Confirmations\UnconfirmController@debtPayment')->name('payments.unconfirm')->where('id', '[0-9]+');
            Route::post('inbound/{id}/unconfirm', 'Confirmations\UnconfirmController@fuelInbound')->name('inbound.unconfirm')->where('id', '[0-9]+');
            Route::post('outbound/{id}/unconfirm', 'Confirmations\UnconfirmController@fuelOutbound')->name('outbound.unconfirm')->where('id', '[0-9]+');
            Route::post('loans/settlements/{id}/unconfirm', 'Confirmations\UnconfirmController@ticketSettlement')->name('loans.settlements.unconfirm')->where('id', '[0-9a-fA-F-]{36}');
        });

        Route::get('card-sales', 'Sales\CardSaleController@index')->name('card-sales.index')->middleware('can:card_sales.view');
        Route::delete('card-sales/{id}', 'Sales\CardSaleController@destroy')->name('card-sales.destroy')->middleware('can:card_sales.delete');

        Route::get('cards', 'Cards\CardController@index')->name('cards.index')->middleware('can:cards.view');
        Route::get('cards/{id}/history', 'Cards\CardController@history')->name('cards.history')->middleware('can:cards.view');
        Route::middleware('can:cards.manage')->group(function () {
            Route::get('cards/{id}/edit', 'Cards\CardController@edit')->name('cards.edit');
            Route::put('cards/{id}', 'Cards\CardController@update')->name('cards.update');
            Route::post('cards/{id}/unlock', 'Cards\CardController@unlock')->name('cards.unlock');
            Route::post('cards/{id}/block', 'Cards\CardController@block')->name('cards.block');
            Route::post('cards/{id}/unblock', 'Cards\CardController@unblock')->name('cards.unblock');
            Route::get('cards/{id}/pin', 'Cards\CardController@pinForm')->name('cards.pin');
            Route::post('cards/{id}/pin', 'Cards\CardController@pinSave')->name('cards.pin.save');
        });
        Route::middleware('can:cards.free')->group(function () {
            Route::post('cards/{id}/free', 'Cards\CardController@free')->name('cards.free');
            Route::post('cards/{id}/unfree', 'Cards\CardController@unfree')->name('cards.unfree');
        });
        Route::get('card-topups', 'Cards\TopUpController@index')->name('card-topups.index')->middleware('can:cards.view');
        Route::get('card-summary', 'Cards\CardSummaryController@index')->name('card-summary.index')->middleware('can:cards.view');
        Route::get('card-summary/export', 'Cards\CardSummaryController@export')->name('card-summary.export')->middleware('can:cards.view');
        Route::middleware('can:cards.topup')->group(function () {
            Route::get('card-topups/create', 'Cards\TopUpController@create')->name('card-topups.create');
            Route::post('card-topups', 'Cards\TopUpController@store')->name('card-topups.store');
        });

        Route::get('payments', 'Debts\DebtController@index')->name('payments.index')->middleware('can:payments.view');
        Route::get('payments/{id}', 'Debts\DebtController@showPayment')->name('payments.show')->middleware('can:payments.view')->where('id', '[0-9]+');
        Route::get('debts/{id}', 'Debts\DebtController@show')->name('debts.show')->middleware('can:payments.view')->where('id', '[0-9a-fA-F-]{36}');
        Route::get('debt-summary', 'Debts\DebtSummaryController@index')->name('debt-summary.index')->middleware('can:payments.view');
        Route::get('debt-aging', 'Debts\DebtAgingController@index')->name('debt-aging.index')->middleware('can:payments.view');
        Route::get('debt-statement', 'Debts\DebtStatementController@show')->name('debt-statement.show')->middleware('can:payments.view');
        Route::get('debt-summary/export', 'Debts\DebtSummaryController@export')->name('debt-summary.export')->middleware('can:payments.view');
        Route::middleware('can:payments.manage')->group(function () {
            Route::get('payments/create', 'Debts\DebtController@create')->name('payments.create');
            Route::get('payments/{id}/edit', 'Debts\DebtController@edit')->name('payments.edit')->where('id', '[0-9]+');
            Route::put('payments/{id}', 'Debts\DebtController@update')->name('payments.update')->where('id', '[0-9]+');
            Route::post('payments', 'Debts\DebtController@store')->name('payments.store');
            Route::post('payments/{id}/reverse', 'Debts\DebtController@reverse')->name('payments.reverse')->where('id', '[0-9]+');
        });

        Route::get('supplements', 'Supplements\SupplementController@index')->name('supplements.index')->middleware('can:supplements.view');
        Route::get('remainders', 'Supplements\RemainderController@index')->name('remainders.index')->middleware('can:supplements.view');
        // the comment: the manager and the accountant (and the admin); everything else of a supply or a remainder: the admin only
        Route::middleware('can:supplements.comment')->group(function () {
            Route::put('remainders/{id}/comment', 'Supplements\RemainderController@updateComment')->name('remainders.comment')->where('id', '[0-9a-fA-F-]{36}');
            Route::get('supplements/{id}/edit', 'Supplements\SupplementController@edit')->name('supplements.edit')->where('id', '[0-9a-fA-F-]{36}');
            Route::put('supplements/{id}', 'Supplements\SupplementController@update')->name('supplements.update')->where('id', '[0-9a-fA-F-]{36}');
        });
        Route::middleware('can:supplements.manage')->group(function () {
            Route::get('remainders/create', 'Supplements\RemainderController@create')->name('remainders.create');
            Route::post('remainders', 'Supplements\RemainderController@store')->name('remainders.store');
            Route::delete('supplements/{id}', 'Supplements\SupplementController@destroy')->name('supplements.destroy')->where('id', '[0-9a-fA-F-]{36}');
        });
        Route::get('supplements/{id}', 'Supplements\SupplementController@show')->name('supplements.show')->middleware('can:supplements.view')->where('id', '[0-9a-fA-F-]{36}');
        Route::middleware('can:supplements.manage')->group(function () {
            Route::get('supplements/create', 'Supplements\SupplementController@create')->name('supplements.create');
            Route::post('supplements', 'Supplements\SupplementController@store')->name('supplements.store');
        });

        Route::middleware('can:reports.view')->group(function () {
            Route::get('reports', 'Reports\ReportController@index')->name('reports.index');
            foreach (App\Http\Controllers\Reports\ReportController::REPORTS as $report) {
                Route::get("reports/$report", "Reports\ReportController@$report")->name("reports.$report");
            }
        });

        Route::middleware('can:backup.manage')->group(function () {
            Route::get('backup', 'Admin\BackupController@index')->name('backup.index');
            Route::post('backup', 'Admin\BackupController@store')->name('backup.store');
            Route::get('backup/{id}/download', 'Admin\BackupController@download')->name('backup.download');
        });

        Route::middleware('can:balances.manage')->group(function () {
            Route::redirect('balances', '/balances/tanks')->name('balances.index');   // the old single page
            Route::get('balances/go-live', 'Initialize\BalanceController@goLive')->name('balances.golive');
            Route::post('balances/go-live', 'Initialize\BalanceController@saveGoLive')->name('balances.golive.save');
            Route::get('balances/tanks', 'Initialize\BalanceController@tanks')->name('balances.tanks.index');
            Route::get('balances/debts', 'Initialize\BalanceController@debts')->name('balances.debts.index');
            Route::get('balances/loans', 'Initialize\BalanceController@loans')->name('balances.loans.index');
            Route::get('balances/ticket-sales', 'Initialize\BalanceController@ticketSales')->name('balances.ticket-sales.index');
            Route::post('balances/ticket-sales', 'Initialize\BalanceController@storeTicketSale')->name('balances.ticket-sales.store');
            Route::get('balances/ticket-sales/{id}/edit', 'Initialize\BalanceController@editTicketSale')->name('balances.ticket-sales.edit')->where('id', '[0-9]+');
            Route::put('balances/ticket-sales/{id}', 'Initialize\BalanceController@updateTicketSale')->name('balances.ticket-sales.update')->where('id', '[0-9]+');
            Route::delete('balances/ticket-sales/{id}', 'Initialize\BalanceController@destroyTicketSale')->name('balances.ticket-sales.destroy')->where('id', '[0-9]+');
            Route::get('balances/remainders','Initialize\BalanceController@remainders')->name('balances.remainders.index');
            Route::post('balances/remainders', 'Initialize\BalanceController@storeRemainder')->name('balances.remainders.store');
            Route::post('balances/tanks', 'Initialize\BalanceController@storeTank')->name('balances.tanks.store');
            Route::get('balances/tanks/{id}/edit', 'Initialize\BalanceController@editTank')->name('balances.tanks.edit')->where('id', '[0-9]+');
            Route::post('balances/tanks/{id}/default', 'Initialize\BalanceController@makeDefaultTank')->name('balances.tanks.default')->where('id', '[0-9]+');
            Route::put('balances/tanks/{id}', 'Initialize\BalanceController@updateTank')->name('balances.tanks.update')->where('id', '[0-9]+');
            Route::delete('balances/tanks/{id}', 'Initialize\BalanceController@destroyTank')->name('balances.tanks.destroy')->where('id', '[0-9]+');
            Route::post('balances/debts', 'Initialize\BalanceController@storeDebt')->name('balances.debts.store');
            Route::get('balances/debts/{id}/edit', 'Initialize\BalanceController@editDebt')->name('balances.debts.edit')->where('id', '[0-9a-fA-F-]{36}');
            Route::put('balances/debts/{id}', 'Initialize\BalanceController@updateDebt')->name('balances.debts.update')->where('id', '[0-9a-fA-F-]{36}');
            Route::delete('balances/debts/{id}', 'Initialize\BalanceController@destroyDebt')->name('balances.debts.destroy');
            Route::post('balances/loans', 'Initialize\BalanceController@storeLoan')->name('balances.loans.store');
            Route::get('balances/loans/{id}/edit', 'Initialize\BalanceController@editLoan')->name('balances.loans.edit')->where('id', '[0-9a-fA-F-]{36}');
            Route::put('balances/loans/{id}', 'Initialize\BalanceController@updateLoan')->name('balances.loans.update')->where('id', '[0-9a-fA-F-]{36}');
            Route::delete('balances/loans/{id}', 'Initialize\BalanceController@destroyLoan')->name('balances.loans.destroy');
        });

        Route::middleware('can:confirmations.manage')->group(function () {
            Route::get('confirmations', 'Confirmations\ConfirmationController@index')->name('confirmations.index');
            Route::get('confirmations/sales-export', 'Confirmations\ConfirmationController@salesExport')->name('confirmations.sales-export');
            Route::get('confirmations/supplements', 'Confirmations\ConfirmationController@supplements')->name('confirmations.supplements');
            Route::get('confirmations/tickets', 'Confirmations\ConfirmationController@tickets')->name('confirmations.tickets');
            Route::get('confirmations/overview', 'Confirmations\ConfirmationController@overview')->name('confirmations.overview');
            Route::get('confirmations/fuel', 'Confirmations\ConfirmationController@fuel')->name('confirmations.fuel');
            Route::get('confirmations/debts', 'Confirmations\ConfirmationController@debts')->name('confirmations.debts');
            Route::get('confirmations/supplements-export', 'Confirmations\ConfirmationController@supplementsExport')->name('confirmations.supplements-export');
            Route::get('confirmations/remainders-export', 'Confirmations\ConfirmationController@remaindersExport')->name('confirmations.remainders-export');
            Route::get('confirmations/summary-export', 'Confirmations\ConfirmationController@summaryExport')->name('confirmations.summary-export');
            Route::get('confirmations/summary', 'Confirmations\ConfirmationController@summaryPage')->name('confirmations.summary');
            Route::post('confirmations', 'Confirmations\ConfirmationController@store')->name('confirmations.store');
        });

        Route::get('audit', 'Admin\AuditController@index')->name('audit.index')->middleware('can:audit.view');

        Route::get('sync', 'Admin\SyncController@index')->name('sync.index')->middleware('can:sync.view');
        Route::post('sync/errors/{id}/resolve', 'Admin\SyncController@resolve')->name('sync.resolve')->middleware('can:sync.manage');

        Route::get('loans', 'Debts\LoanController@index')->name('loans.index')->middleware('can:loans.view');
        Route::get('loans/settlements/{id}', 'Debts\LoanController@showSettlement')->name('loans.settlements.show')->middleware('can:loans.view')->where('id', '[0-9a-fA-F-]{36}');
        Route::get('loans/{id}', 'Debts\LoanController@show')->name('loans.show')->middleware('can:loans.view')->where('id', '[0-9a-fA-F-]{36}');
        Route::middleware('can:loans.manage')->group(function () {
            Route::get('loans/create', 'Debts\LoanController@create')->name('loans.create');
            Route::post('loans', 'Debts\LoanController@store')->name('loans.store');
            Route::post('loans/settlements/{id}/reverse', 'Debts\LoanController@reverse')->name('loans.settlements.reverse');
        });

        $crud('users', 'Admin\UserController', 'users');
        $crud('fuel', 'Admin\FuelTypeController', 'fuel');
        $crud('tanks', 'Admin\TankController', 'fuel');
        Route::get('nozzles', 'Admin\NozzleController@index')->name('nozzles.index')->middleware('can:fuel.view'); // listed only: the Smart210 manages them
        $crud('customers', 'Sales\CustomerController', 'customers');
        $crud('devices', 'Admin\DeviceController', 'devices');
    });
});
