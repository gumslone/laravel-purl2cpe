<?php

namespace Gumslone\Purl2Cpe\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string basePurl(string $purl)
 * @method static string[] candidates(string $purl, ?string $version = null, ?bool $heuristic = null)
 * @method static string|null toCpe23(string $purl, ?string $version = null, ?bool $heuristic = null)
 * @method static string|null toCpe22Uri(string $purl, ?string $version = null, ?bool $heuristic = null)
 * @method static array{cpe: ?string, source: ?string} resolve(string $purl, ?string $version = null, ?bool $heuristic = null)
 * @method static array{vendor: string, product: string}|null vendorProduct(string $purl, ?bool $heuristic = null)
 * @method static array<array{vendor: string, product: string}> vendorProducts(string $purl)
 * @method static string|null heuristicCpe23(string $purl, ?string $version = null)
 * @method static string|null heuristicCpe22Uri(string $purl, ?string $version = null)
 * @method static array{vendor: string, product: string}|null heuristicVendorProduct(string $purl)
 * @method static array{vendor: string, product: string} splitVendorProduct(string $cpe23)
 * @method static bool isMapped(string $purl)
 * @method static string injectVersion(string $baseCpe, ?string $version)
 * @method static string cpe23ToCpe22(string $cpe23)
 * @method static int count()
 * @method static int importBundled()
 * @method static int importFromSource(string $sqlitePath)
 *
 * @see \Gumslone\Purl2Cpe\Purl2Cpe
 */
class Purl2Cpe extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gumslone\Purl2Cpe\Purl2Cpe::class;
    }
}
