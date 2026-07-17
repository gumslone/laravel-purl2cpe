# laravel-purl2cpe

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Convert [Package URLs (PURLs)](https://github.com/package-url/purl-spec) to
[CPE](https://nvd.nist.gov/products/cpe) names in Laravel, backed by the
curated [scanoss/purl2cpe](https://github.com/scanoss/purl2cpe) database.

Deriving a CPE from a package name heuristically (`vendor:product`) is
notoriously inaccurate — `chart.js` is `cpe:2.3:a:chartjs:chart.js`, not
`chart.js:chart.js`. This package ships a **reduced snapshot of ~47,000
curated mappings** so you can resolve the real NVD CPE for a package offline,
then feed it to NVD / CVE-Search / any CPE-keyed vulnerability source.

The upstream database is 512 MB of version-specific rows; this package reduces
it to the distinct `base PURL → base CPE` pairs (≈700 KB gzipped) and
substitutes the package version at lookup time.

## Installation

Once published on [Packagist](https://packagist.org/):

```bash
composer require gumslone/laravel-purl2cpe
```

Until then (or to track `master` directly), add the repository to your app's
`composer.json` and require it:

```jsonc
"repositories": [
    { "type": "vcs", "url": "https://github.com/gumslone/laravel-purl2cpe" }
]
```

```bash
composer require gumslone/laravel-purl2cpe:dev-master
```

Run the migration and load the bundled mappings:

```bash
php artisan migrate
php artisan purl2cpe:import
```

That's it — `purl2cpe:import` reads the snapshot shipped with the package, no
network access required.

## Usage

Via the facade:

```php
use Purl2Cpe;

Purl2Cpe::toCpe23('pkg:composer/laravel/framework@11.55.0', '11.55.0');
// "cpe:2.3:a:laravel:framework:11.55.0:*:*:*:*:*:*:*"

Purl2Cpe::toCpe22Uri('pkg:npm/axios@1.7.0', '1.7.0');
// "cpe:/a:axios:axios:1.7.0"

Purl2Cpe::candidates('pkg:gem/http@5.0.0', '5.0.0');
// packages can map to several vendor:product pairs
// ["cpe:2.3:a:httprb:http:5.0.0:...", "cpe:2.3:a:http.rb_project:http.rb:5.0.0:..."]

Purl2Cpe::isMapped('pkg:composer/laravel/framework@11.55.0'); // true
```

Or inject the service:

```php
use Gumslone\Purl2Cpe\Purl2Cpe;

class Scanner
{
    public function __construct(private readonly Purl2Cpe $purl2cpe) {}

    public function cpeFor(string $purl, ?string $version): ?string
    {
        return $this->purl2cpe->toCpe23($purl, $version);
    }
}
```

The version can be passed explicitly or read from the PURL — the base PURL
(type/namespace/name) is used for the lookup and the version is injected into
field 6 of the CPE. If you omit the version you get the wildcard-version CPE.

## API

| Method | Returns | Description |
| --- | --- | --- |
| `toCpe23($purl, $version = null)` | `?string` | Best curated CPE 2.3, version injected |
| `toCpe22Uri($purl, $version = null)` | `?string` | Best curated CPE 2.2 URI |
| `candidates($purl, $version = null)` | `string[]` | All vendor:product candidates |
| `isMapped($purl)` | `bool` | Whether the PURL is in the catalog |
| `basePurl($purl)` | `string` | PURL with version/qualifiers stripped |
| `injectVersion($baseCpe, $version)` | `string` | Substitute a version into a base CPE |
| `cpe23ToCpe22($cpe23)` | `string` | Convert a 2.3 string to a 2.2 URI |
| `count()` | `int` | Number of mappings loaded |

## Refreshing from upstream

The bundled snapshot is a point-in-time copy. To rebuild from the latest
upstream data (downloads ~50 MB, expands to ~512 MB temporarily):

```bash
php artisan purl2cpe:sync
```

Or reduce a database you already have:

```bash
php artisan purl2cpe:sync --db=/path/to/purl2cpe.db
```

Schedule it to stay current:

```php
// routes/console.php
Schedule::command('purl2cpe:sync')->monthly();
```

## Configuration

Publish the config to change the table name or database connection:

```bash
php artisan vendor:publish --tag=purl2cpe-config
```

```php
return [
    'connection' => env('PURL2CPE_CONNECTION'),      // null = default connection
    'table'      => env('PURL2CPE_TABLE', 'purl_cpe_mappings'),
    'db_url'     => env('PURL2CPE_DB_URL', 'https://github.com/scanoss/purl2cpe/raw/main/purl2cpe.db.zip'),
];
```

## How the reduction works

The upstream `purl2cpe.db` maps each base PURL to one CPE row *per known
version* (2.6M rows). Since a base CPE differs from its versions only in
field 6, this package keeps just the distinct
`(purl, cpe:2.3:a:vendor:product:*:...)` pairs and substitutes the real
version on lookup. Only application CPEs (`part = a`) are imported.

## Testing

```bash
composer install
composer test
```

## Credits

- Mapping data: [scanoss/purl2cpe](https://github.com/scanoss/purl2cpe) (MIT)
- CPE and PURL are specifications of NIST and the Package URL project respectively.

## License

MIT. See [LICENSE](LICENSE). The bundled mapping data is derived from
scanoss/purl2cpe, also MIT licensed.
