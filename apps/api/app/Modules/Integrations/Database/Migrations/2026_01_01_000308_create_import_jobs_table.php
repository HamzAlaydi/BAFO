<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.4 `import_jobs` (§14.7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 40); // enum:ImportType
            $table->string('mode', 40); // enum:ImportMode
            $table->string('status', 40); // enum:JobStatus
            $table->foreignId('source_file_id')->constrained('files')->restrictOnDelete();
            $table->foreignId('errors_file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('created_rows')->default(0);
            $table->integer('updated_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->jsonb('errors_preview')->nullable(); // first 100 {row, column, code, message}
            $table->string('failure_message', 500)->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
