<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.5 `comments` (Q&A; one level of replies).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('author_organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('author_participant_id')->nullable()->constrained('participants')->restrictOnDelete(); // null when the issuer writes
            $table->boolean('is_issuer')->default(false);
            $table->text('body'); // 1–2000 chars
            $table->timestampsTz();

            $table->index(['competition_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
