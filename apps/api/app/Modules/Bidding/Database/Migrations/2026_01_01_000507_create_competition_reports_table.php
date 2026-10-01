<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.6 `competition_reports` (the result PDF per locale, §7.14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('status', 40); // enum:ReportStatus
            $table->foreignId('file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->bigInteger('live_version'); // the live-state version that was rendered
            $table->timestampTz('generated_at')->nullable();
            $table->timestampsTz();

            $table->unique(['competition_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_reports');
    }
};
