# Changelog

All notable changes to `laravel-purl2cpe` are documented here.
This project adheres to [Semantic Versioning](https://semver.org).

## v1.0.0

Initial release.

- Curated PURL → CPE resolution backed by the
  [scanoss/purl2cpe](https://github.com/scanoss/purl2cpe) database, reduced to
  ~47k distinct base mappings and **bundled** (≈700 KB gzipped) for offline use.
- `Purl2Cpe` service + facade:
  - `toCpe23()`, `toCpe22Uri()`, `candidates()`, `isMapped()`.
  - `vendorProduct()`, `vendorProducts()`, `splitVendorProduct()` — convert a
    PURL (or CPE string) straight to CPE vendor + product.
  - `basePurl()`, `injectVersion()`, `cpe23ToCpe22()`, `count()`.
  - `importBundled()`, `importFromSource()`.
- Artisan commands: `purl2cpe:import` (load the bundled snapshot, no network)
  and `purl2cpe:sync` (rebuild from the upstream database, or `--db=` a local
  copy).
- Publishable config and migration; configurable table + connection.
- Case-insensitive base-PURL fallback for inconsistent upstream casing;
  deterministic (ordered) candidate output.
