# laravel-purl2cpe

[![tests](https://github.com/gumslone/laravel-purl2cpe/actions/workflows/tests.yml/badge.svg)](https://github.com/gumslone/laravel-purl2cpe/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate/?hosted_button_id=VCWHQPACTXV5N)

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

```bash
composer require gumslone/laravel-purl2cpe
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

Just need the CPE **vendor and product** for a PURL?

```php
Purl2Cpe::vendorProduct('pkg:composer/laravel/framework');
// ['vendor' => 'laravel', 'product' => 'framework']

Purl2Cpe::vendorProduct('pkg:npm/chart.js');
// ['vendor' => 'chartjs', 'product' => 'chart.js']

Purl2Cpe::vendorProducts('pkg:gem/http');
// [['vendor' => 'httprb', 'product' => 'http'], ['vendor' => 'http.rb_project', 'product' => 'http.rb']]
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

### Heuristic fallback (optional)

The curated catalog only covers packages that have a known NVD entry. For the
rest, you can **optionally** derive a best-effort CPE straight from the PURL —
vendor and product are inferred from its namespace and name:

```php
// Off by default: an unmapped PURL returns null
Purl2Cpe::toCpe23('pkg:composer/acme/widget@1.2.3', '1.2.3'); // null

// Opt in per call (3rd argument), or globally via config
Purl2Cpe::toCpe23('pkg:composer/acme/widget@1.2.3', '1.2.3', heuristic: true);
// "cpe:2.3:a:acme:widget:1.2.3:*:*:*:*:*:*:*"

// npm scope -> vendor "babel"; Go module owner -> vendor "gin-gonic"
Purl2Cpe::heuristicVendorProduct('pkg:npm/%40babel/core');            // ['vendor' => 'babel', 'product' => 'core']
Purl2Cpe::heuristicVendorProduct('pkg:golang/github.com%2Fgin-gonic/gin'); // ['vendor' => 'gin-gonic', 'product' => 'gin']
```

A heuristic CPE is a **guess** that may match an NVD record, not an authoritative
mapping. Use `resolve()` when you want to know which strategy produced a CPE so
you can label or double-check the guessed ones:

```php
Purl2Cpe::resolve('pkg:composer/laravel/framework@11.0', '11.0', heuristic: true);
// ['cpe' => 'cpe:2.3:a:laravel:framework:11.0:...', 'source' => 'curated']

Purl2Cpe::resolve('pkg:composer/acme/widget@1.0', '1.0', heuristic: true);
// ['cpe' => 'cpe:2.3:a:acme:widget:1.0:...', 'source' => 'heuristic']
```

Enable the fallback for every call by setting `purl2cpe.heuristic_fallback` to
`true` (or `PURL2CPE_HEURISTIC_FALLBACK=true`). The per-call `$heuristic`
argument always overrides the config. The `heuristic*` methods ignore the flag —
they always guess — while the `resolve()`/`toCpe*`/`vendorProduct` methods only
guess when the catalog misses.

## API

| Method | Returns | Description |
| --- | --- | --- |
| `toCpe23($purl, $version = null, $heuristic = null)` | `?string` | Best CPE 2.3, version injected (heuristic on catalog miss) |
| `toCpe22Uri($purl, $version = null, $heuristic = null)` | `?string` | Best CPE 2.2 URI |
| `resolve($purl, $version = null, $heuristic = null)` | `array{cpe,source}` | CPE plus its `source`: `curated`, `heuristic`, or null |
| `vendorProduct($purl, $heuristic = null)` | `?array{vendor,product}` | CPE vendor + product for a PURL |
| `vendorProducts($purl)` | `array<array{vendor,product}>` | All curated vendor/product pairs |
| `candidates($purl, $version = null, $heuristic = null)` | `string[]` | All CPE candidates, version injected |
| `isMapped($purl)` | `bool` | Whether the PURL is in the curated catalog |
| `heuristicCpe23($purl, $version = null)` | `?string` | Guess a CPE 2.3 from the PURL, no lookup |
| `heuristicCpe22Uri($purl, $version = null)` | `?string` | Guess a CPE 2.2 URI from the PURL |
| `heuristicVendorProduct($purl)` | `?array{vendor,product}` | Guess vendor + product from the PURL |
| `splitVendorProduct($cpe23)` | `array{vendor,product}` | Vendor + product of a CPE 2.3 string |
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

## Support

If this package saves you time, consider supporting its development:

<p align="center">
  <a href="https://www.buymeacoffee.com/gumslone" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-orange.png" alt="Buy Me A Coffee" height="41" width="174"></a>
</p>

[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate/?hosted_button_id=VCWHQPACTXV5N)

## License

MIT. See [LICENSE](LICENSE). The bundled mapping data is derived from
scanoss/purl2cpe, also MIT licensed.
