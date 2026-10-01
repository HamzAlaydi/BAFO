<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.4 `export_jobs` (§14.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 40); // enum:ExportType
            $table->string('format', 40); // enum:ExportFormat
            $table->jsonb('filters'); // e.g. {competition_id} (public id), {from, to}
            $table->string('status', 40); // enum:JobStatus
            $table->foreignId('file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->integer('row_count')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
