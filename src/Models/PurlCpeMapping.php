<?php

namespace Gumslone\Purl2Cpe\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A curated PURL → base-CPE mapping.
 *
 * The PURL is stored without a version; the CPE carries a wildcard version
 * that is substituted with the package's actual version at resolution time.
 *
 * @property string $purl
 * @property string $cpe
 */
class PurlCpeMapping extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return config('purl2cpe.connection') ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return config('purl2cpe.table', 'purl_cpe_mappings');
    }
}
