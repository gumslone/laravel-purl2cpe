<?php

namespace Gumslone\Purl2Cpe;

/**
 * Derives a plausible CPE 2.3 name from a PURL alone, with no catalog lookup.
 *
 * This is a best-effort heuristic: vendor and product are inferred from the
 * PURL's namespace and name. It is intended as a fallback for packages the
 * curated scanoss/purl2cpe database does not map — a guessed CPE that may match
 * an NVD record, not an authoritative one. Treat its output as a candidate to
 * verify, not a confirmed identifier.
 */
class HeuristicResolver
{
    /**
     * Build a heuristic CPE 2.3 string, or null when vendor/product can't be
     * inferred from the PURL.
     */
    public function cpe23(string $purl, ?string $version = null): ?string
    {
        $vp = $this->vendorProduct($purl);
        if ($vp === null) {
            return null;
        }

        $v = ($version !== null && $version !== '') ? $this->sanitiseVersion($version) : '*';

        return implode(':', [
            'cpe', '2.3', 'a', $vp['vendor'], $vp['product'], $v,
            '*', '*', '*', '*', '*', '*', '*',
        ]);
    }

    /**
     * Build the legacy heuristic CPE 2.2 URI, or null.
     */
    public function cpe22Uri(string $purl, ?string $version = null): ?string
    {
        $vp = $this->vendorProduct($purl);
        if ($vp === null) {
            return null;
        }

        $v = ($version !== null && $version !== '') ? $this->sanitiseVersion($version) : '';

        return "cpe:/a:{$vp['vendor']}:{$vp['product']}".($v !== '' ? ":{$v}" : '');
    }

    /**
     * Infer the CPE vendor and product from a PURL, or null when the PURL can't
     * be parsed or yields empty parts.
     *
     * @return array{vendor: string, product: string}|null
     */
    public function vendorProduct(string $purl): ?array
    {
        $parsed = $this->parse($purl);
        if ($parsed === null) {
            return null;
        }

        $vendor = $this->sanitise($this->vendorFrom($parsed));
        $product = $this->sanitise($parsed['name']);

        if ($vendor === '' || $product === '') {
            return null;
        }

        return ['vendor' => $vendor, 'product' => $product];
    }

    /**
     * Parse a PURL into its type, namespace, and name. Version, qualifiers, and
     * subpath are discarded. Percent-encoded segments (e.g. an npm scope
     * "%40babel" or a Go module path) are decoded.
     *
     * @return array{type: string, namespace: ?string, name: string}|null
     */
    private function parse(string $purl): ?array
    {
        // Strip scheme, then version/qualifiers/subpath.
        $body = preg_replace('/^pkg:/i', '', trim($purl));
        $body = (string) preg_replace('/[@?#].*$/', '', (string) $body);
        $body = trim($body, '/');

        if ($body === '' || ! str_contains($body, '/')) {
            // Need at least type + name.
            return null;
        }

        [$type, $rest] = explode('/', $body, 2);
        $segments = array_values(array_filter(explode('/', $rest), fn ($s) => $s !== ''));

        if ($type === '' || $segments === []) {
            return null;
        }

        $name = rawurldecode((string) array_pop($segments));
        $namespace = $segments === []
            ? null
            : rawurldecode(implode('/', $segments));

        return ['type' => strtolower($type), 'namespace' => $namespace, 'name' => $name];
    }

    /**
     * Choose the CPE vendor. A namespace is the best source; for VCS-host-style
     * namespaces (Go modules such as "github.com/gin-gonic") the vendor is the
     * repo owner after the host, not the host itself. Without a namespace, the
     * product name doubles as the vendor.
     *
     * @param  array{type: string, namespace: ?string, name: string}  $parsed
     */
    private function vendorFrom(array $parsed): string
    {
        $namespace = $parsed['namespace'];

        if ($namespace === null || $namespace === '') {
            return $parsed['name'];
        }

        $segments = explode('/', $namespace);

        // First segment is a hostname (contains a dot) with an owner after it.
        if (count($segments) > 1 && str_contains($segments[0], '.')) {
            return $segments[1];
        }

        // A single-segment namespace with a dot but no owner (bare host) is
        // useless as a vendor — fall back to the name.
        if (count($segments) === 1 && str_contains($segments[0], '.')) {
            return $parsed['name'];
        }

        return $segments[0];
    }

    /**
     * Sanitise a CPE component: lowercase, non-alphanumeric to underscore, then
     * trim leading/trailing underscores so an npm scope like "@babel" yields
     * "babel", not "_babel" (internal underscores such as "linux_kernel" stay).
     */
    private function sanitise(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9._\-]/', '_', strtolower($value)), '_');
    }

    private function sanitiseVersion(string $version): string
    {
        return ltrim($version, 'vV');
    }
}
