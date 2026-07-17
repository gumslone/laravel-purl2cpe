<?php

namespace Gumslone\Purl2Cpe\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string basePurl(string $purl)
 * @method static string[] candidates(string $purl, ?string $version = null)
 * @method static string|null toCpe23(string $purl, ?string $version = null)
 * @method static string|null toCpe22Uri(string $purl, ?string $version = null)
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
