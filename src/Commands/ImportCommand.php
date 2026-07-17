<?php

namespace Gumslone\Purl2Cpe\Commands;

use Gumslone\Purl2Cpe\Purl2Cpe;
use Illuminate\Console\Command;

/**
 * Load the PURL→CPE mappings bundled with the package (no network needed).
 */
class ImportCommand extends Command
{
    protected $signature = 'purl2cpe:import';

    protected $description = 'Load the bundled PURL→CPE mappings into the database';

    public function handle(Purl2Cpe $purl2cpe): int
    {
        $this->info('Importing bundled purl2cpe mappings…');

        try {
            $count = $purl2cpe->importBundled();
        } catch (\Throwable $exception) {
            $this->error('Import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$count} PURL→CPE mappings.");

        return self::SUCCESS;
    }
}
