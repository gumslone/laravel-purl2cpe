# Changelog

All notable changes to `laravel-purl2cpe` are documented here.
This project adheres to [Semantic Versioning](https://semver.org).

## v1.1.0

Adds optional **heuristic** CPE conversion for PURLs the curated catalog does not
map. Fully backward compatible — the fallback is off by default.

- New `HeuristicResolver`: derives a best-effort CPE 2.3/2.2 straight from a
  PURL's namespace and name (npm scopes, Go module host paths, and bare names
  handled), with no catalog lookup.
- `Purl2Cpe`:
  - `toCpe23()`, `toCpe22Uri()`, `candidates()`, `vendorProduct()` gain an
    optional `$heuristic` argument to fall back to a guess on a catalog miss.
  - `resolve()` — returns the CPE **and** its `source` (`curated` / `heuristic`
    / null) so guessed CPEs can be labelled or double-checked.
  - `heuristicCpe23()`, `heuristicCpe22Uri()`, `heuristicVendorProduct()` —
    always-on guessing, regardless of config.
- New `purl2cpe.heuristic_fallback` config (`PURL2CPE_HEURISTIC_FALLBACK`),
  default `false`; the per-call argument overrides it.

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
