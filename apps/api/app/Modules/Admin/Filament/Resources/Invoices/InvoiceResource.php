<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Invoices;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Modules\Admin\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Billing\Actions\IssueEInvoice;
use App\Modules\Billing\Actions\RecordCreditNote;
use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Support\Files\File;
use App\Support\Files\FileStorage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * §16 Invoices: list with the e-invoice status, download. Retry e-invoice → Billing
 * `IssueEInvoice`; Record credit note (manual, the provider's document reference) →
 * `RecordCreditNote`.
 */
final class InvoiceResource extends AdminResource
{
    protected static ?string $model = Invoice::class;

    protected static string $langKey = 'invoices';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Billing;

    protected static ?int $navigationSort = 50;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'number';

    /**
     * Soft-deleted organizations stay visible (account deletion keeps their records).
     *
     * @return Builder<Invoice>
     */
    public static function getEloquentQuery(): Builder
    {
        return Invoice::query()->with(self::withOrganization());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->withExists('creditNotes'))
            ->columns([
                TextColumn::make('number')->label(self::field('number'))->searchable()->fontFamily('mono'),
                TextColumn::make('type')->label(self::field('type'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('organization.name')->label(self::field('organization'))->searchable(),
                TextColumn::make('total_minor')->label(self::field('total'))
                    ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null),
                TextColumn::make('einvoice_status')->label(self::field('einvoice_status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('einvoice_attempts')->label(self::field('einvoice_attempts'))->toggleable(),
                TextColumn::make('issue_date')->label(self::field('issue_date'))->date('Y-m-d')->sortable(),
                TextColumn::make('issued_at')->label(self::field('issued_at'))->dateTime(Display::DATE_TIME)->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('einvoice_status')->label(self::field('einvoice_status'))->multiple()
                    ->options(Display::options(EInvoiceStatus::class)),
                SelectFilter::make('type')->label(self::field('type'))->options(Display::options(InvoiceType::class)),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(self::invoiceActions()),
            ])
            ->defaultSort('issued_at', 'desc');
    }

    /**
     * @return list<Action>
     */
    public static function invoiceActions(): array
    {
        return [
            Action::make('download')
                ->label(Lang::get('resources.invoices.actions.download'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(static fn (Invoice $record): bool => $record->pdf_file_id !== null)
                ->action(static function (Invoice $record): ?StreamedResponse {
                    $file = File::query()->find($record->pdf_file_id);

                    return $file instanceof File ? app(FileStorage::class)->download($file) : null;
                }),
            Action::make('retryEInvoice')
                ->label(Lang::get('resources.invoices.actions.retry'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(static fn (Invoice $record): bool => $record->type === InvoiceType::TaxInvoice && (
                    in_array($record->einvoice_status, [EInvoiceStatus::Pending, EInvoiceStatus::Rejected, EInvoiceStatus::Failed], true)
                    || $record->pdf_file_id === null
                ))
                ->requiresConfirmation()
                ->action(static function (Invoice $record): void {
                    $invoice = ModuleAction::run(static fn () => app(IssueEInvoice::class)->handle($record, AdminActor::current()));

                    ModuleAction::notify(in_array($invoice->einvoice_status, [EInvoiceStatus::Cleared, EInvoiceStatus::Reported], true)
                        ? Lang::get('resources.invoices.notifications.cleared')
                        : Lang::get('resources.invoices.notifications.still_failing'), $invoice->einvoice_last_error);
                }),
            Action::make('recordCreditNote')
                ->label(Lang::get('resources.invoices.actions.credit_note'))
                ->icon(Heroicon::OutlinedReceiptRefund)
                ->color('danger')
                ->visible(static fn (Invoice $record): bool => $record->type === InvoiceType::TaxInvoice && ! $record->creditNotes()->exists())
                ->requiresConfirmation()
                ->modalDescription(Lang::get('resources.invoices.actions.credit_note_help'))
                ->schema([
                    TextInput::make('reference')->label(Lang::get('fields.provider_document_id'))->required()->maxLength(120),
                ])
                ->action(static fn (Invoice $record, array $data) => ModuleAction::run(
                    static fn () => app(RecordCreditNote::class)->handle($record, trim((string) $data['reference']), AdminActor::current()),
                    Lang::get('resources.invoices.notifications.credit_note_recorded'),
                )),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null;

        return $schema->components([
            Section::make(self::field('section_invoice'))->columns(4)->schema([
                TextEntry::make('number')->label(self::field('number'))->fontFamily('mono'),
                TextEntry::make('type')->label(self::field('type'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextEntry::make('organization.name')->label(self::field('organization'))
                    ->url(static fn (Invoice $record): ?string => OrganizationResource::link($record->organization)),
                TextEntry::make('originalInvoice.number')->label(self::field('original_invoice'))->placeholder('—'),
                TextEntry::make('issue_date')->label(self::field('issue_date'))->date('Y-m-d'),
                TextEntry::make('supply_date')->label(self::field('supply_date'))->date('Y-m-d'),
                TextEntry::make('subtotal_minor')->label(self::field('subtotal'))->formatStateUsing($money),
                TextEntry::make('discount_minor')->label(self::field('discount'))->formatStateUsing($money),
                TextEntry::make('vat_minor')->label(self::field('vat'))->formatStateUsing($money),
                TextEntry::make('total_minor')->label(self::field('total'))->formatStateUsing($money)->weight('bold'),
            ]),
            Section::make(self::field('section_einvoice'))->columns(4)->schema([
                TextEntry::make('einvoice_provider')->label(self::field('einvoice_provider')),
                TextEntry::make('einvoice_status')->label(self::field('einvoice_status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextEntry::make('einvoice_attempts')->label(self::field('einvoice_attempts')),
                TextEntry::make('einvoice_document_id')->label(self::field('einvoice_document_id'))->placeholder('—'),
                TextEntry::make('zatca_uuid')->label(self::field('zatca_uuid'))->placeholder('—'),
                TextEntry::make('cleared_at')->label(self::field('cleared_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('einvoice_last_error')->label(self::field('einvoice_last_error'))->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
