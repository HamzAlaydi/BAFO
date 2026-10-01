<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 150);
            $table->string('email', 255);
            $table->string('phone', 20)->nullable();
            $table->string('company', 150)->nullable();
            $table->string('subject', 150);
            $table->text('message');
            $table->string('locale', 2);
            $table->string('status', 40)->default('new');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            // ref → admins (unconstrained: later module)
            $table->unsignedBigInteger('handled_by_admin_id')->nullable()->index();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
