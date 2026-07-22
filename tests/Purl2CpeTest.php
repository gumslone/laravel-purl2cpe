<?php

use Gumslone\Purl2Cpe\Purl2Cpe;
use Illuminate\Support\Facades\DB;
use Purl2Cpe as Purl2CpeFacade;

function seedMappings(array $rows): void
{
    DB::table('purl_cpe_mappings')->insert(
        collect($rows)->map(fn ($r) => ['purl' => $r[0], 'cpe' => $r[1]])->all()
    );
}

it('strips version, qualifiers and subpath to the base purl', function () {
    $service = app(Purl2Cpe::class);

    expect($service->basePurl('pkg:composer/laravel/framework@11.55.0'))->toBe('pkg:composer/laravel/framework')
        ->and($service->basePurl('pkg:npm/%40babel/core@7.0.0'))->toBe('pkg:npm/%40babel/core')
        ->and($service->basePurl('pkg:gem/http@1.0?platform=ruby#sub'))->toBe('pkg:gem/http');
});

it('resolves a purl to a versioned CPE 2.3 and 2.2', function () {
    seedMappings([['pkg:composer/laravel/framework', 'cpe:2.3:a:laravel:framework:*:*:*:*:*:*:*:*']]);

    $service = app(Purl2Cpe::class);

    expect($service->toCpe23('pkg:composer/laravel/framework@11.55.0', '11.55.0'))
        ->toBe('cpe:2.3:a:laravel:framework:11.55.0:*:*:*:*:*:*:*')
        ->and($service->toCpe23('pkg:composer/laravel/framework@v9.0', 'v9.0'))
        ->toBe('cpe:2.3:a:laravel:framework:9.0:*:*:*:*:*:*:*')
        ->and($service->toCpe22Uri('pkg:composer/laravel/framework@11.55.0', '11.55.0'))
        ->toBe('cpe:/a:laravel:framework:11.55.0')
        ->and($service->isMapped('pkg:composer/laravel/framework@1.0'))->toBeTrue()
        ->and($service->toCpe23('pkg:composer/acme/unknown@1.0', '1.0'))->toBeNull()
        ->and($service->isMapped('pkg:composer/acme/unknown'))->toBeFalse();
});

it('converts a purl to its CPE vendor and product', function () {
    seedMappings([
        ['pkg:composer/laravel/framework', 'cpe:2.3:a:laravel:framework:*:*:*:*:*:*:*:*'],
        ['pkg:gem/http', 'cpe:2.3:a:httprb:http:*:*:*:*:*:*:*:*'],
        ['pkg:gem/http', 'cpe:2.3:a:http.rb_project:http.rb:*:*:*:*:*:*:*:*'],
    ]);

    $service = app(Purl2Cpe::class);

    expect($service->vendorProduct('pkg:composer/laravel/framework@11.55.0'))
        ->toBe(['vendor' => 'laravel', 'product' => 'framework'])
        ->and($service->vendorProduct('pkg:composer/acme/unknown@1.0'))->toBeNull()
        ->and($service->vendorProducts('pkg:gem/http'))->toBe([
            ['vendor' => 'http.rb_project', 'product' => 'http.rb'],
            ['vendor' => 'httprb', 'product' => 'http'],
        ]);
});

it('returns every vendor:product candidate for a purl', function () {
    seedMappings([
        ['pkg:gem/http', 'cpe:2.3:a:httprb:http:*:*:*:*:*:*:*:*'],
        ['pkg:gem/http', 'cpe:2.3:a:http.rb_project:http.rb:*:*:*:*:*:*:*:*'],
    ]);

    // ordered by CPE for deterministic output
    expect(app(Purl2Cpe::class)->candidates('pkg:gem/http@5.0.0', '5.0.0'))
        ->toBe([
            'cpe:2.3:a:http.rb_project:http.rb:5.0.0:*:*:*:*:*:*:*',
            'cpe:2.3:a:httprb:http:5.0.0:*:*:*:*:*:*:*',
        ]);
});

it('falls back to a lowercased base purl (upstream casing is inconsistent)', function () {
    seedMappings([['pkg:deb/debian/optipng', 'cpe:2.3:a:optipng:optipng:*:*:*:*:*:*:*:*']]);

    expect(app(Purl2Cpe::class)->toCpe23('pkg:Deb/debian/optipng@1.0', '1.0'))
        ->toBe('cpe:2.3:a:optipng:optipng:1.0:*:*:*:*:*:*:*');
});

it('exposes a working facade', function () {
    seedMappings([['pkg:npm/axios', 'cpe:2.3:a:axios:axios:*:*:*:*:*:*:*:*']]);

    expect(Purl2CpeFacade::toCpe23('pkg:npm/axios@1.7.0', '1.7.0'))
        ->toBe('cpe:2.3:a:axios:axios:1.7.0:*:*:*:*:*:*:*');
});

it('imports the bundled snapshot end to end', function () {
    $count = app(Purl2Cpe::class)->importBundled();

    expect($count)->toBeGreaterThan(40000)
        ->and(app(Purl2Cpe::class)->count())->toBe($count)
        // A well-known mapping resolves against the real bundled data
        ->and(app(Purl2Cpe::class)->toCpe23('pkg:composer/laravel/framework@11.55.0', '11.55.0'))
        ->toBe('cpe:2.3:a:laravel:framework:11.55.0:*:*:*:*:*:*:*');
});

it('rebuilds mappings from an upstream-shaped sqlite source', function () {
    $path = tempnam(sys_get_temp_dir(), 'p2c_src_').'.db';
    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE purl2cpe (purl TEXT, cpe TEXT, UNIQUE(purl,cpe))');
    $pdo->exec("INSERT INTO purl2cpe VALUES
        ('pkg:composer/laravel/framework','cpe:2.3:a:laravel:framework:10.0.0:*:*:*:*:*:*:*'),
        ('pkg:composer/laravel/framework','cpe:2.3:a:laravel:framework:10.1.0:*:*:*:*:*:*:*'),
        ('pkg:deb/linux','cpe:2.3:o:linux:linux_kernel:5.0:*:*:*:*:*:*:*')");
    unset($pdo);

    $count = app(Purl2Cpe::class)->importFromSource($path);

    expect($count)->toBe(1) // deduped to one base CPE; the OS row is dropped
        ->and(app(Purl2Cpe::class)->toCpe23('pkg:composer/laravel/framework@11.0', '11.0'))
        ->toBe('cpe:2.3:a:laravel:framework:11.0:*:*:*:*:*:*:*');

    @unlink($path);
});

/*
|--------------------------------------------------------------------------
| Heuristic fallback
|--------------------------------------------------------------------------
*/

it('does not guess a CPE by default when the catalog misses', function () {
    $service = app(Purl2Cpe::class);

    expect($service->toCpe23('pkg:composer/acme/widget@1.0', '1.0'))->toBeNull()
        ->and($service->candidates('pkg:composer/acme/widget@1.0', '1.0'))->toBe([])
        ->and($service->vendorProduct('pkg:composer/acme/widget'))->toBeNull();
});

it('derives a heuristic CPE from the purl when fallback is enabled per call', function () {
    $service = app(Purl2Cpe::class);

    expect($service->toCpe23('pkg:composer/acme/widget@1.2.3', '1.2.3', true))
        ->toBe('cpe:2.3:a:acme:widget:1.2.3:*:*:*:*:*:*:*')
        ->and($service->toCpe22Uri('pkg:composer/acme/widget@1.2.3', '1.2.3', true))
        ->toBe('cpe:/a:acme:widget:1.2.3')
        ->and($service->vendorProduct('pkg:composer/acme/widget', true))
        ->toBe(['vendor' => 'acme', 'product' => 'widget']);
});

it('honours the config flag for heuristic fallback', function () {
    config()->set('purl2cpe.heuristic_fallback', true);

    expect(app(Purl2Cpe::class)->toCpe23('pkg:pypi/django@5.0', '5.0'))
        ->toBe('cpe:2.3:a:django:django:5.0:*:*:*:*:*:*:*');
});

it('prefers a curated mapping over a heuristic guess', function () {
    seedMappings([['pkg:composer/laravel/framework', 'cpe:2.3:a:laravel:framework:*:*:*:*:*:*:*:*']]);

    // Even with fallback on, the real mapping wins (curated vendor "laravel").
    $result = app(Purl2Cpe::class)->resolve('pkg:composer/laravel/framework@11.0', '11.0', true);

    expect($result['source'])->toBe('curated')
        ->and($result['cpe'])->toBe('cpe:2.3:a:laravel:framework:11.0:*:*:*:*:*:*:*');
});

it('reports the heuristic source when the catalog misses', function () {
    $result = app(Purl2Cpe::class)->resolve('pkg:composer/acme/widget@1.0', '1.0', true);

    expect($result['source'])->toBe('heuristic')
        ->and($result['cpe'])->toBe('cpe:2.3:a:acme:widget:1.0:*:*:*:*:*:*:*');

    // And null all round when even a guess is impossible.
    expect(app(Purl2Cpe::class)->resolve('pkg:npm/left-pad', null, false))
        ->toBe(['cpe' => null, 'source' => null]);
});

it('infers vendor from an npm scope, a Go host path, and a bare name', function () {
    $service = app(Purl2Cpe::class);

    expect($service->heuristicCpe23('pkg:npm/%40babel/core@7.0', '7.0'))
        // "@babel" scope -> vendor "babel", not "_babel"
        ->toBe('cpe:2.3:a:babel:core:7.0:*:*:*:*:*:*:*')
        ->and($service->heuristicVendorProduct('pkg:golang/github.com%2Fgin-gonic/gin'))
        // Go module: owner after the host is the vendor
        ->toBe(['vendor' => 'gin-gonic', 'product' => 'gin'])
        ->and($service->heuristicCpe23('pkg:npm/left-pad@1.3.0', '1.3.0'))
        // No namespace: the name doubles as the vendor
        ->toBe('cpe:2.3:a:left-pad:left-pad:1.3.0:*:*:*:*:*:*:*');
});

it('derives a Maven vendor from the reverse-DNS group', function () {
    $service = app(Purl2Cpe::class);

    // 3+ label groups: the second label is the conventional NVD-style vendor.
    expect($service->heuristicVendorProduct('pkg:maven/org.apache.commons/commons-lang3'))
        ->toBe(['vendor' => 'apache', 'product' => 'commons-lang3'])
        ->and($service->heuristicVendorProduct('pkg:maven/com.google.guava/guava'))
        ->toBe(['vendor' => 'google', 'product' => 'guava'])
        // A short dotted group is kept whole rather than mis-guessed.
        ->and($service->heuristicVendorProduct('pkg:maven/io.netty/netty-common'))
        ->toBe(['vendor' => 'io.netty', 'product' => 'netty-common']);
});

it('returns null for a purl the heuristic cannot parse', function () {
    expect(app(Purl2Cpe::class)->heuristicCpe23('not-a-purl'))->toBeNull()
        ->and(app(Purl2Cpe::class)->heuristicVendorProduct('pkg:'))->toBeNull();
});
