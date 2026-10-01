<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.5 `competition_attachments`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_attachments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->string('kind', 40); // enum:AttachmentKind
            $table->foreignId('file_id')->nullable()->constrained('files')->restrictOnDelete(); // null for external_link
            $table->string('title', 200)->nullable();
            $table->string('url', 1000)->nullable(); // https; external_link only
            $table->boolean('is_addendum')->default(false);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_attachments');
    }
};
