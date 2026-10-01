<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.3 `consents` (no timestamps; `accepted_at` is the record time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->string('document_code', 40); // enum:LegalDocumentCode (Platform)
            $table->string('document_version', 20);
            $table->string('locale', 2);
            $table->timestampTz('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
