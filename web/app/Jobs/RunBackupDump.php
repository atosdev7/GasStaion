<?php

namespace App\Jobs;

use App\Support\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

// The backup that follows a fully confirmed day: a safety dump of the whole database (the same as the nightly one). It is dispatched after the
// response (ConfirmationController), so the accountant does not wait for it; with a real queue driver it runs on the worker.
class RunBackupDump implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $note;

    public function __construct($note = '')
    {
        $this->note = $note;
    }

    public function handle()
    {
        if (Artisan::call('backup:dump', ['--note' => $this->note]) !== 0) {
            Audit::log('backup_dump_failed', 'after the confirmation: ' . $this->note, 'system');
        }
    }
}
