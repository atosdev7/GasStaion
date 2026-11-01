<?php

// Smart210 API (JSON). A device is recognised by its IP address, which the admin sets on the Devices screen. Ping is open
// (it also says whether the caller's address is registered); everything else needs a registered address.
Route::get('ping', 'Api\PingController@ping');

Route::middleware('device')->group(function () {
    Route::get('device', 'Api\DeviceInfoController@show');

    // Everything that moves data waits (503 "busy") while a backup runs.
    Route::middleware('sync.open')->group(function () {
        // Pull: small config whole, big tables page by page with acks.
        Route::get('pull/config', 'Api\PullController@config');
        Route::get('pull/{table}', 'Api\PullController@page')->where('table', App\Support\Sync\Pull::pattern());
        Route::post('pull/{table}/ack', 'Api\PullController@ack')->where('table', App\Support\Sync\Pull::pattern());
        Route::post('confirmed', 'Api\PullController@confirmed');
        Route::post('sync/begin', 'Api\PullController@begin');
        Route::post('sync/initial-done', 'Api\PullController@initialDone');

        // Push: a batch of rows of one table; the reply says which were saved and which rejected.
        Route::post('push/{table}', 'Api\PushController@store')->where('table', App\Support\Sync\Push::pattern());
        // The terminal reports that nothing of the days before the running business day is left to upload (the backup waits for it).
        Route::post('sync/uploaded', 'Api\PushController@uploaded');
    });
});
