<?php

namespace App\Console\Commands;

use App\Services\Views\EarningsReleaser;
use Illuminate\Console\Command;

class ReleaseMaturedEarnings extends Command
{
    protected $signature = 'earnings:release';

    protected $description = 'Release pending view earnings whose review period has ended';

    public function handle(EarningsReleaser $releaser): int
    {
        $this->info('Released '.$releaser->release().' views.');

        return self::SUCCESS;
    }
}
