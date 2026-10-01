<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.3 `otp_codes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255); // lowercased; indexed by the composite below
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 40); // enum:OtpPurpose
            $table->char('code_hash', 64); // hash_hmac('sha256', code, config('app.key'))
            $table->jsonb('context')->nullable();
            $table->smallInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestampTz('created_at');

            $table->index(['email', 'purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
