<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Actions\IssueEInvoice;
use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Support\Auth\Actor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * `billing:retry-einvoices` (every five minutes, ARCHITECTURE §12): invoices with
 * `einvoice_status ∈ {pending, failed}` and fewer than 10 attempts are issued again; accepted
 * invoices whose PDF is missing get it rendered.
 */
final class RetryEInvoicesCommand extends Command
{
    protected $signature = 'billing:retry-einvoices';

    protected $description = 'Retry pending or failed e-invoices';

    public function handle(IssueEInvoice $issue): int
    {
        $actor = Actor::system();
        $count = 0;

        Invoice::query()
            ->where(static function (Builder $query): void {
                $query->where(static function (Builder $retry): void {
                    $retry->whereIn('einvoice_status', [EInvoiceStatus::Pending->value, EInvoiceStatus::Failed->value])
                        ->where('einvoice_attempts', '<', IssueEInvoice::MAX_ATTEMPTS);
                })->orWhere(static function (Builder $pdf): void {
                    $pdf->whereIn('einvoice_status', [EInvoiceStatus::Cleared->value, EInvoiceStatus::Reported->value])
                        ->whereNull('pdf_file_id');
                });
            })
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($issue, $actor, &$count): void {
                $issue->handle($invoice, $actor);
                $count++;
            });

        $this->components->info("Processed {$count} invoices.");

        return self::SUCCESS;
    }
}
