<?php

namespace Gumslone\Purl2Cpe\Commands;

use Gumslone\Purl2Cpe\Purl2Cpe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ZipArchive;

/**
 * Rebuild the mappings from the upstream scanoss/purl2cpe database.
 *
 * Downloads the ~50 MB archive (expands to ~512 MB), reduces it to distinct
 * base CPEs, and replaces the table. Use `purl2cpe:import` instead if the
 * bundled snapshot is enough — this command is for pulling fresh upstream
 * data or building from a local copy via `--db`.
 */
class SyncCommand extends Command
{
    protected $signature = 'purl2cpe:sync {--db= : Path to an already-downloaded purl2cpe SQLite file (skips download)}';

    protected $description = 'Rebuild PURL→CPE mappings from the upstream scanoss/purl2cpe database';

    public function handle(Purl2Cpe $purl2cpe): int
    {
        $cleanup = [];

        try {
            $sqlitePath = $this->option('db') ?: null;

            if ($sqlitePath === null) {
                $this->info('Downloading upstream purl2cpe database (~50 MB)…');
                [$sqlitePath, $cleanup] = $this->download();
            }

            $this->info('Reducing and importing mappings…');
            $count = $purl2cpe->importFromSource($sqlitePath);

            $this->info("Imported {$count} PURL→CPE mappings.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Sync failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            foreach ($cleanup as $path) {
                File::delete($path);
            }
        }
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function download(): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('The zip extension is required to download the upstream database');
        }

        $url = (string) config('purl2cpe.db_url');
        $zipPath = tempnam(sys_get_temp_dir(), 'purl2cpe_').'.zip';

        $response = Http::timeout(600)->sink($zipPath)->get($url);
        if (! $response->successful()) {
            File::delete($zipPath);
            throw new \RuntimeException("Download failed (HTTP {$response->status()})");
        }

        $extractDir = $zipPath.'_extracted';
        File::ensureDirectoryExists($extractDir);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Downloaded archive is not a valid zip');
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $dbFiles = File::glob($extractDir.'/*.db');
        if ($dbFiles === []) {
            throw new \RuntimeException('No .db file found inside the archive');
        }

        return [$dbFiles[0], [$zipPath, $dbFiles[0], $extractDir]];
    }
}
