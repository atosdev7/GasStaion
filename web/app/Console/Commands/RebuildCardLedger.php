<?php

namespace App\Console\Commands;

use App\Support\Admin\Audit;
use App\Support\Cards\CardLedger;
use Illuminate\Console\Command;

// Makes the ledger entries of the top-ups and fills that have none (nothing is made twice, nothing is removed) and works the running balances out
// again. A repair: the ledger is kept by the model events of Sale and Supplement.
class RebuildCardLedger extends Command
{
    protected $signature = 'cards:rebuild-ledger';

    protected $description = 'Make the card ledger entries that are missing and work the running balances out again';

    public function handle()
    {
        $made = CardLedger::rebuild();
        foreach (\App\Models\Cards\Card::withTrashed()->pluck('id') as $cardId) {
            CardLedger::recompute($cardId);
        }
        Audit::log('cards_rebuild_ledger', $made . ' entries made', 'system');
        $this->info($made . ' entries made.');

        return 0;
    }
}
