<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RELEASE_SCOPE.md §2.2: presets gain a nullable `tier` (simple | standard | protected). The
 * reference presets of ARCHITECTURE §5.2 keep `tier = null`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_presets', function (Blueprint $table): void {
            $table->string('tier', 20)->nullable(); // enum:PresetTier
        });
    }

    public function down(): void
    {
        Schema::table('competition_presets', function (Blueprint $table): void {
            $table->dropColumn('tier');
        });
    }
};
