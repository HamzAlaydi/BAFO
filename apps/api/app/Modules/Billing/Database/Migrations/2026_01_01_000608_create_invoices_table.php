<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.7 `invoices` (§13.7) and the sequence `invoice_number_seq`
 * (`BAFO-INV-{YYYY}-{nextval padded to 6}`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // `migrate:fresh` drops tables but not free-standing sequences: recreate it from 1.
        DB::statement('DROP SEQUENCE IF EXISTS invoice_number_seq');
        DB::statement('CREATE SEQUENCE invoice_number_seq START 1');

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('number', 30)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete(); // buyer
            $table->foreignId('payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->string('type', 40)->default('tax_invoice'); // enum:InvoiceType
            $table->foreignId('original_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->date('issue_date'); // Riyadh date
            $table->date('supply_date');
            $table->char('currency', 3);
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor'); // includes the pro-rata credit
            $table->bigInteger('vat_minor');
            $table->bigInteger('total_minor');
            $table->integer('vat_rate_bp');
            $table->jsonb('seller_snapshot'); // config bafo.billing.seller
            $table->jsonb('buyer_snapshot'); // legal names, CR, VAT, address
            $table->string('einvoice_provider', 20);
            $table->string('einvoice_status', 40)->default('pending'); // enum:EInvoiceStatus
            $table->string('einvoice_document_id', 120)->nullable();
            $table->uuid('zatca_uuid')->nullable();
            $table->text('qr_payload')->nullable(); // base64 TLV
            $table->smallInteger('einvoice_attempts')->default(0);
            $table->text('einvoice_last_error')->nullable();
            $table->foreignId('pdf_file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->timestampTz('issued_at');
            $table->timestampTz('cleared_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        DB::statement('DROP SEQUENCE IF EXISTS invoice_number_seq');
    }
};
