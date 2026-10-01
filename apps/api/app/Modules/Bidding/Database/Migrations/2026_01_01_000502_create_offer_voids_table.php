<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.6 `offer_voids` (append-only; trigger). Platform admin voids only (§7.13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_voids', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('offer_id')->unique()->constrained('offers')->restrictOnDelete();
            $table->foreignId('reason_id')->constrained('close_reasons')->restrictOnDelete(); // kind void_offer
            $table->text('note')->nullable(); // required when the reason requires_note
            $table->unsignedBigInteger('voided_by_admin_id')->index(); // ref → admins (unconstrained: later module)
            $table->timestampTz('created_at', 6);
        });

        DB::statement('CREATE TRIGGER offer_voids_append_only BEFORE UPDATE OR DELETE ON offer_voids FOR EACH ROW EXECUTE FUNCTION bafo_forbid_update_delete()');
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_voids');
    }
};
