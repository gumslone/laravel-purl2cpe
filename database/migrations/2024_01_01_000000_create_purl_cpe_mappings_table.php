<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('purl2cpe.connection');
    }

    public function up(): void
    {
        Schema::create(config('purl2cpe.table', 'purl_cpe_mappings'), function (Blueprint $table) {
            $table->id();
            $table->string('purl')->comment('Base PURL, no version/qualifiers, e.g. pkg:composer/laravel/framework');
            $table->string('cpe')->comment('Base CPE 2.3 with a wildcard version');

            $table->unique(['purl', 'cpe']);
            $table->index('purl');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('purl2cpe.table', 'purl_cpe_mappings'));
    }
};
