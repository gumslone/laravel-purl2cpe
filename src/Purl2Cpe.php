<?php

namespace Gumslone\Purl2Cpe;

use Gumslone\Purl2Cpe\Models\PurlCpeMapping;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Convert Package URLs (PURLs) to Common Platform Enumeration (CPE) names
 * using the curated scanoss/purl2cpe mapping database.
 *
 * The mapping table stores base PURLs (no version) against base CPE 2.3
 * strings (wildcard version). Resolution substitutes the package's actual
 * version, so a single mapping row serves every version of a package.
 */
class Purl2Cpe
{
    /**
     * Strip version, qualifiers, and subpath from a PURL, leaving the base
     * `pkg:type/namespace/name` the mapping table is keyed on.
     */
    public function basePurl(string $purl): string
    {
        return rtrim((string) preg_replace('/[@?#].*$/', '', $purl), '/');
    }

    /**
     * All curated CPE 2.3 candidates for a PURL, with the given version
     * substituted in. A package may map to several vendor:product pairs.
     * Empty when the PURL is not in the catalog.
     *
     * @return string[]
     */
    public function candidates(string $purl, ?string $version = null): array
    {
        $base = $this->basePurl($purl);

        $bases = $this->query()
            ->whereIn('purl', array_unique([$base, strtolower($base)]))
            ->orderBy('cpe')
            ->pluck('cpe')
            ->unique();

        return $bases
            ->map(fn (string $cpe) => $this->injectVersion($cpe, $version))
            ->values()
            ->all();
    }

    /**
     * The best curated CPE 2.3 for a PURL, or null when unmapped.
     */
    public function toCpe23(string $purl, ?string $version = null): ?string
    {
        return $this->candidates($purl, $version)[0] ?? null;
    }

    /**
     * The best curated CPE 2.2 URI for a PURL, or null when unmapped.
     */
    public function toCpe22Uri(string $purl, ?string $version = null): ?string
    {
        $cpe23 = $this->toCpe23($purl, $version);

        return $cpe23 ? $this->cpe23ToCpe22($cpe23) : null;
    }

    /**
     * The CPE vendor and product for a PURL, from the best curated CPE.
     * Null when the PURL is not in the catalog.
     *
     * @return array{vendor: string, product: string}|null
     */
    public function vendorProduct(string $purl): ?array
    {
        $cpe = $this->toCpe23($purl);

        return $cpe ? $this->splitVendorProduct($cpe) : null;
    }

    /**
     * Every distinct CPE vendor/product pair for a PURL (a package can map to
     * more than one). Empty when the PURL is not in the catalog.
     *
     * @return array<array{vendor: string, product: string}>
     */
    public function vendorProducts(string $purl): array
    {
        $pairs = array_map(
            fn (string $cpe) => $this->splitVendorProduct($cpe),
            $this->candidates($purl),
        );

        return array_values(array_unique($pairs, SORT_REGULAR));
    }

    /**
     * Whether the PURL has at least one curated CPE mapping.
     */
    public function isMapped(string $purl): bool
    {
        $base = $this->basePurl($purl);

        return $this->query()
            ->whereIn('purl', array_unique([$base, strtolower($base)]))
            ->exists();
    }

    /**
     * Substitute a concrete version into a base CPE 2.3 (field 6).
     */
    public function injectVersion(string $baseCpe, ?string $version): string
    {
        $parts = explode(':', $baseCpe);
        if (count($parts) >= 6) {
            $parts[5] = ($version !== null && $version !== '')
                ? $this->sanitiseVersion($version)
                : '*';
        }

        return implode(':', $parts);
    }

    /**
     * Convert a CPE 2.3 formatted string to the legacy CPE 2.2 URI.
     */
    public function cpe23ToCpe22(string $cpe23): string
    {
        $parts = explode(':', $cpe23);
        $part = $parts[2] ?? 'a';
        $vendor = $parts[3] ?? '*';
        $product = $parts[4] ?? '*';
        $version = $parts[5] ?? '*';

        $uri = "cpe:/{$part}:{$vendor}:{$product}";

        return $version !== '*' ? "{$uri}:{$version}" : $uri;
    }

    /**
     * Extract the vendor (field 4) and product (field 5) of a CPE 2.3 string.
     *
     * @return array{vendor: string, product: string}
     */
    public function splitVendorProduct(string $cpe23): array
    {
        $parts = explode(':', $cpe23);

        return ['vendor' => $parts[3] ?? '', 'product' => $parts[4] ?? ''];
    }

    /**
     * Number of mapping rows currently loaded.
     */
    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * Load the mappings bundled with this package (gzipped CSV). Replaces the
     * table contents. Returns the number of rows imported.
     */
    public function importBundled(): int
    {
        $path = dirname(__DIR__).'/database/data/purl2cpe.csv.gz';

        if (! is_file($path)) {
            throw new \RuntimeException("Bundled purl2cpe data not found at {$path}");
        }

        $handle = gzopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open the bundled purl2cpe data');
        }

        $this->query()->truncate();

        $imported = 0;
        $buffer = [];

        while (($line = gzgets($handle)) !== false) {
            $row = str_getcsv(rtrim($line, "\r\n"));
            if (count($row) < 2 || $row[0] === '' || $row[1] === '') {
                continue;
            }

            $buffer[] = ['purl' => $row[0], 'cpe' => $row[1]];
            if (count($buffer) >= 1000) {
                $this->query()->insertOrIgnore($buffer);
                $imported += count($buffer);
                $buffer = [];
            }
        }
        gzclose($handle);

        if ($buffer !== []) {
            $this->query()->insertOrIgnore($buffer);
            $imported += count($buffer);
        }

        return $imported;
    }

    /**
     * Rebuild the mappings from an upstream scanoss/purl2cpe SQLite file
     * (as produced by the `purl2cpe.db.zip` archive). Reduces the millions
     * of version-specific rows to distinct base CPEs. Returns the row count.
     */
    public function importFromSource(string $sqlitePath): int
    {
        if (! is_file($sqlitePath)) {
            throw new \RuntimeException("purl2cpe source database not found at {$sqlitePath}");
        }

        $pdo = new PDO('sqlite:'.$sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Reduce version-specific rows to distinct (base purl, base CPE) by
        // extracting the vendor (field 4) and product (field 5) of each CPE
        // and rebuilding a wildcard-version CPE 2.3. Only application (part
        // 'a') CPEs are kept.
        $sql = <<<'SQL'
            SELECT DISTINCT purl, 'cpe:2.3:a:' || v || ':' || p || ':*:*:*:*:*:*:*:*' AS cpe
            FROM (
                SELECT purl,
                    substr(r, 1, instr(r, ':') - 1) AS v,
                    substr(r2, 1, instr(r2 || ':', ':') - 1) AS p
                FROM (
                    SELECT purl, r, substr(r, instr(r, ':') + 1) AS r2
                    FROM (
                        SELECT purl, substr(cpe, 11) AS r
                        FROM purl2cpe
                        WHERE cpe LIKE 'cpe:2.3:a:%:%:%'
                    )
                )
            )
            WHERE v <> '' AND p <> ''
            SQL;

        $statement = $pdo->query($sql);

        $this->query()->truncate();

        $imported = 0;
        $buffer = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $buffer[] = ['purl' => $row['purl'], 'cpe' => $row['cpe']];
            if (count($buffer) >= 1000) {
                $this->query()->insertOrIgnore($buffer);
                $imported += count($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $this->query()->insertOrIgnore($buffer);
            $imported += count($buffer);
        }

        return $imported;
    }

    private function sanitiseVersion(string $version): string
    {
        return ltrim($version, 'vV');
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(config('purl2cpe.connection'))
            ->table(config('purl2cpe.table', 'purl_cpe_mappings'));
    }
}
