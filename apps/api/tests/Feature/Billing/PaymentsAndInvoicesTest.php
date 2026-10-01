<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\InvoicePayment;
use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Billing\Billing;

beforeEach(fn () => Storage::fake('private'));

describe('GET /billing/payments/{payment}', function () {
    it('shows the payment to the payer even without billing.view', function () {
        $organization = Organization::factory()->create();
        $payer = Billing::member($organization, OrgRole::Member, ['can_purchase' => true]);
        $payment = Payment::factory()->create(['organization_id' => $organization->id, 'created_by_user_id' => $payer->id]);
        Billing::actingAs($payer);

        $this->getJson('/api/app/v1/billing/payments/'.$payment->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $payment->public_id)
            ->assertJsonPath('data.context.intent', null);
    });

    it('shows the payment to billing viewers of the organization', function () {
        $payment = Payment::factory()->create();
        Billing::actingAs(Billing::member($payment->organization, OrgRole::Admin));

        $this->getJson('/api/app/v1/billing/payments/'.$payment->public_id)->assertOk();
    });

    it('forbids other members of the organization', function () {
        $payment = Payment::factory()->create();
        Billing::actingAs(Billing::member($payment->organization, OrgRole::Member));

        $this->getJson('/api/app/v1/billing/payments/'.$payment->public_id)->assertForbidden();
    });

    it('hides another organization payment as 404', function () {
        $payment = Payment::factory()->create();
        Billing::actingAs(Billing::member());

        $this->getJson('/api/app/v1/billing/payments/'.$payment->public_id)->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->postJson('/api/app/v1/billing/payments/'.$payment->public_id.'/verify')->assertNotFound();
    });

    it('answers 404 for unknown ids', function () {
        Billing::actingAs(Billing::member());

        $this->getJson('/api/app/v1/billing/payments/not-a-ulid')->assertNotFound();
    });

    it('requires authentication', function () {
        $payment = Payment::factory()->create();

        $this->getJson('/api/app/v1/billing/payments/'.$payment->public_id)->assertUnauthorized();
        $this->postJson('/api/app/v1/billing/payments/'.$payment->public_id.'/verify')->assertUnauthorized();
    });
});

describe('invoices', function () {
    /**
     * An invoiced, succeeded payment of the organization.
     */
    function billingInvoicedPayment(Organization $organization): Invoice
    {
        $payment = Payment::factory()->succeeded()->create(['organization_id' => $organization->id]);
        $payment->lines()->create([
            'kind' => 'plan', 'description' => ['ar' => 'باقة برو — شهري', 'en' => 'Pro plan, monthly'],
            'quantity' => 1, 'unit_price_minor' => 150_000, 'net_minor' => 150_000,
        ]);

        return app(InvoicePayment::class)->handle($payment, Actor::system()) ?? throw new RuntimeException('no invoice');
    }

    it('lists the organization invoices newest first, paginated', function () {
        $owner = Billing::actingAs(Billing::member());
        $organization = $owner->membership->organization;
        $older = billingInvoicedPayment($organization);
        $older->forceFill(['issued_at' => now()->subDay()])->save();
        $newer = billingInvoicedPayment($organization);
        billingInvoicedPayment(Organization::factory()->create());

        $this->getJson('/api/app/v1/billing/invoices?per_page=1', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'number', 'type', 'issue_date', 'currency', 'subtotal_minor', 'discount_minor', 'vat_rate_bp',
                'vat_minor', 'total_minor', 'einvoice_status', 'zatca_uuid', 'lines' => [['description', 'quantity', 'unit_price_minor', 'net_minor']],
                'pdf' => ['available', 'download_path'], 'payment_id', 'issued_at']], 'meta' => ['pagination']])
            ->assertJsonPath('data.0.id', $newer->public_id)
            ->assertJsonPath('data.0.lines.0.description', 'Pro plan, monthly')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.has_more', true);
    });

    it('answers 409 for the PDF until the e-invoice is cleared', function () {
        $owner = Billing::actingAs(Billing::member());
        $invoice = billingInvoicedPayment($owner->membership->organization);
        $invoice->forceFill(['einvoice_status' => EInvoiceStatus::Pending, 'pdf_file_id' => null])->save();

        $this->getJson('/api/app/v1/billing/invoices/'.$invoice->public_id)->assertOk()->assertJsonPath('data.pdf.available', false);
        $this->getJson('/api/app/v1/billing/invoices/'.$invoice->public_id.'/pdf')
            ->assertStatus(409)
            ->assertJsonPath('code', 'invoice_pdf_not_ready');
    });

    it('hides another organization invoice as 404', function () {
        $invoice = billingInvoicedPayment(Organization::factory()->create());
        Billing::actingAs(Billing::member());

        $this->getJson('/api/app/v1/billing/invoices/'.$invoice->public_id)->assertNotFound();
        $this->getJson('/api/app/v1/billing/invoices/'.$invoice->public_id.'/pdf')->assertNotFound();
        $this->get('/api/app/v1/files/'.$invoice->pdfFile?->public_id.'/download')->assertForbidden();
    });

    it('requires billing.view', function () {
        $owner = Billing::member();
        $invoice = billingInvoicedPayment($owner->membership->organization);
        Billing::actingAs(Billing::member($owner->membership->organization, OrgRole::Member));

        $this->getJson('/api/app/v1/billing/invoices')->assertForbidden();
        $this->getJson('/api/app/v1/billing/invoices/'.$invoice->public_id)->assertForbidden();
        $this->get('/api/app/v1/files/'.$invoice->pdfFile?->public_id.'/download')->assertForbidden();
    });

    it('requires authentication', function () {
        $this->getJson('/api/app/v1/billing/invoices')->assertUnauthorized();
    });

    it('skips zero-total payments and never invoices twice', function () {
        $organization = Organization::factory()->create();
        $free = Payment::factory()->succeeded()->create(['organization_id' => $organization->id, 'subtotal_minor' => 0, 'vat_minor' => 0, 'total_minor' => 0]);

        expect(app(InvoicePayment::class)->handle($free, Actor::system()))->toBeNull();

        $invoice = billingInvoicedPayment($organization);
        expect(app(InvoicePayment::class)->handle($invoice->payment, Actor::system())?->id)->toBe($invoice->id)
            ->and(Invoice::query()->where('organization_id', $organization->id)->count())->toBe(1);
    });
});
