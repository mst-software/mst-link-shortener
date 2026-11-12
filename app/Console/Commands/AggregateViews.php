<?php

namespace App\Console\Commands;

use App\Services\Views\ViewAggregator;
use Illuminate\Console\Command;

class AggregateViews extends Command
{
    protected $signature = 'views:aggregate';

    protected $description = 'Fold valid views into daily stats per user and day';

    public function handle(ViewAggregator $aggregator): int
    {
        $this->info('Aggregated '.$aggregator->aggregate().' views.');

        return self::SUCCESS;
    }
}
