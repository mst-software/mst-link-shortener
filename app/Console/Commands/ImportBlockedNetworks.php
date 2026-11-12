<?php

namespace App\Console\Commands;

use App\Models\BlockedNetwork;
use App\Services\Views\BlockedNetworkChecker;
use Illuminate\Console\Command;

class ImportBlockedNetworks extends Command
{
    protected $signature = 'networks:import {file : Text file with one CIDR per line, optionally followed by "# label"}';

    protected $description = 'Import blocked networks from a text file, replacing the label of CIDRs that already exist';

    public function handle(BlockedNetworkChecker $checker): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error('File not found or not readable: '.$path);

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$cidr, $label] = array_pad(explode('#', $line, 2), 2, null);
            $cidr = trim($cidr);
            $label = $label === null ? null : trim($label);

            if (! BlockedNetwork::isValidCidr($cidr)) {
                $skipped++;

                continue;
            }

            BlockedNetwork::query()->updateOrCreate(
                ['cidr' => $cidr],
                ['label' => $label === '' ? null : $label],
            );
            $imported++;
        }

        $checker->clearCache();

        $this->info('Imported '.$imported.' networks, skipped '.$skipped.' invalid lines.');

        return self::SUCCESS;
    }
}
