<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 40);
            $table->string('locale', 2);
            $table->string('version', 20);
            $table->string('title', 200);
            $table->text('body_markdown');
            $table->timestampTz('published_at')->nullable();
            // ref → admins (unconstrained: later module)
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            $table->timestampsTz();

            $table->unique(['code', 'locale', 'version']);
            $table->index(['code', 'locale', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
