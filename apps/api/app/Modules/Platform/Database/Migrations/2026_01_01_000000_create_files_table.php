<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            // ref → organizations (unconstrained: later module)
            $table->unsignedBigInteger('organization_id')->nullable();
            // ref → users (unconstrained: later module)
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable()->index();
            $table->string('purpose', 40);
            $table->string('disk', 20);
            $table->string('path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->string('extension', 16);
            $table->bigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestampsTz();

            $table->index(['organization_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
