<?php

namespace App\Console\Commands;

use App\Services\Advertiser\TopUpService;
use Illuminate\Console\Command;

class SyncSepayTopUps extends Command
{
    protected $signature = 'sepay:sync';

    protected $description = 'Credit advertiser wallets for bank transfers found through the SePay API';

    public function handle(TopUpService $topUps): int
    {
        $this->info('Credited '.$topUps->syncSepay().' top ups.');

        return self::SUCCESS;
    }
}
