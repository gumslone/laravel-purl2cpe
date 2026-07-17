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
