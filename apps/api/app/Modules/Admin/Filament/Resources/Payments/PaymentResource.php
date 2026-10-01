<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Payments;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Resources\Payments\Pages\ListPayments;
use App\Modules\Admin\Filament\Resources\Payments\Pages\ViewPayment;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Admin\Support\PaymentsCsv;
use App\Modules\Billing\Actions\MarkPaymentPaid;
use App\Modules\Billing\Actions\ReconcilePayment;
use App\Modules\Billing\Actions\RecordRefund;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Payment;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * §16 Payments: list, filter, CSV export. Reconcile now → `ReconcilePayment`; Mark paid manually
 * (reference) → `MarkPaymentPaid` (HandleGatewayResult with a manual state); Record refund
 * (reference) → `RecordRefund`. All Billing Actions with `Actor::forAdmin()`.
 */
final class PaymentResource extends AdminResource
{
    protected static ?string $model = Payment::class;

    protected static string $langKey = 'payments';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Billing;

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    /**
     * Soft-deleted organizations stay visible (account deletion keeps their records).
     *
     * @return Builder<Payment>
     */
    public static function getEloquentQuery(): Builder
    {
        return Payment::query()->with(self::withOrganization());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')->label(self::field('id'))->searchable()->limit(10)->fontFamily('mono')->copyable(),
                TextColumn::make('organization.name')->label(self::field('organization'))->searchable(),
                TextColumn::make('purpose')->label(self::field('purpose'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('total_minor')->label(self::field('total'))
                    ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null),
                TextColumn::make('gateway')->label(self::field('gateway')),
                TextColumn::make('gateway_reference')->label(self::field('gateway_reference'))->searchable()->placeholder('—')->toggleable(),
                IconColumn::make('needs_review')->label(self::field('needs_review'))->boolean()
                    ->state(static fn (Payment $record): bool => self::needsReview($record)),
                TextColumn::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME)->sortable(),
                TextColumn::make('paid_at')->label(self::field('paid_at'))->dateTime(Display::DATE_TIME)->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(self::field('status'))->multiple()->options(Display::options(PaymentStatus::class)),
                SelectFilter::make('purpose')->label(self::field('purpose'))->options(Display::options(PaymentPurpose::class)),
                SelectFilter::make('gateway')->label(self::field('gateway'))->options(['fake' => 'fake', 'moyasar' => 'moyasar']),
                Filter::make('needs_review')->label(self::field('needs_review'))
                    ->query(static fn (Builder $query): Builder => $query->where('metadata->needs_manual_review', true)),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(self::field('created_from')),
                        DatePicker::make('until')->label(self::field('created_until')),
                    ])
                    ->query(static fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, static fn (Builder $q, string $date): Builder => $q->where('created_at', '>=', self::riyadhDayStart($date)))
                        ->when($data['until'] ?? null, static fn (Builder $q, string $date): Builder => $q->where('created_at', '<', self::riyadhDayStart($date, 1)))),
            ])
            ->headerActions([
                Action::make('exportCsv')
                    ->label(Lang::get('resources.payments.actions.export_csv'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(static function (ListPayments $livewire): ?StreamedResponse {
                        $query = $livewire->getFilteredSortedTableQuery();

                        return $query === null ? null : PaymentsCsv::download($query);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(self::paymentActions()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return list<Action>
     */
    public static function paymentActions(): array
    {
        return [
            Action::make('reconcile')
                ->label(Lang::get('resources.payments.actions.reconcile'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(static fn (Payment $record): bool => in_array($record->status, [PaymentStatus::Pending, PaymentStatus::Expired], true))
                ->requiresConfirmation()
                ->action(static fn (Payment $record) => ModuleAction::run(
                    static fn () => app(ReconcilePayment::class)->handle($record, AdminActor::current()),
                    Lang::get('resources.payments.notifications.reconciled'),
                )),
            Action::make('markPaid')
                ->label(Lang::get('resources.payments.actions.mark_paid'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(static fn (Payment $record): bool => in_array($record->status, [PaymentStatus::Pending, PaymentStatus::Expired], true))
                ->requiresConfirmation()
                ->modalDescription(Lang::get('resources.payments.actions.mark_paid_help'))
                ->schema([
                    TextInput::make('reference')->label(Lang::get('fields.reference'))->required()->maxLength(120),
                ])
                ->action(static fn (Payment $record, array $data) => ModuleAction::run(
                    static fn () => app(MarkPaymentPaid::class)->handle($record, trim((string) $data['reference']), AdminActor::current()),
                    Lang::get('resources.payments.notifications.marked_paid'),
                )),
            Action::make('recordRefund')
                ->label(Lang::get('resources.payments.actions.record_refund'))
                ->icon(Heroicon::OutlinedReceiptRefund)
                ->color('danger')
                ->visible(static fn (Payment $record): bool => $record->status === PaymentStatus::Succeeded)
                ->requiresConfirmation()
                ->modalDescription(Lang::get('resources.payments.actions.record_refund_help'))
                ->schema([
                    TextInput::make('reference')->label(Lang::get('fields.reference'))->required()->maxLength(120),
                ])
                ->action(static fn (Payment $record, array $data) => ModuleAction::run(
                    static fn () => app(RecordRefund::class)->handle($record, trim((string) $data['reference']), AdminActor::current()),
                    Lang::get('resources.payments.notifications.refund_recorded'),
                )),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null;

        return $schema->components([
            Section::make(self::field('section_payment'))->columns(4)->schema([
                TextEntry::make('public_id')->label(self::field('id'))->fontFamily('mono')->copyable(),
                TextEntry::make('organization.name')->label(self::field('organization'))
                    ->url(static fn (Payment $record): ?string => OrganizationResource::link($record->organization)),
                TextEntry::make('purpose')->label(self::field('purpose'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextEntry::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextEntry::make('subtotal_minor')->label(self::field('subtotal'))->formatStateUsing($money),
                TextEntry::make('discount_minor')->label(self::field('discount'))->formatStateUsing($money),
                TextEntry::make('credit_minor')->label(self::field('credit'))->formatStateUsing($money),
                TextEntry::make('vat_minor')->label(self::field('vat'))->formatStateUsing($money),
                TextEntry::make('total_minor')->label(self::field('total'))->formatStateUsing($money)->weight('bold'),
                TextEntry::make('gateway')->label(self::field('gateway')),
                TextEntry::make('gateway_reference')->label(self::field('gateway_reference'))->placeholder('—'),
                IconEntry::make('needs_review')->label(self::field('needs_review'))->boolean()
                    ->state(static fn (Payment $record): bool => self::needsReview($record)),
                TextEntry::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME),
                TextEntry::make('expires_at')->label(self::field('expires_at'))->dateTime(Display::DATE_TIME),
                TextEntry::make('paid_at')->label(self::field('paid_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('failed_at')->label(self::field('failed_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('failure_code')->label(self::field('failure_code'))->placeholder('—'),
                TextEntry::make('failure_message')->label(self::field('failure_message'))->placeholder('—'),
                TextEntry::make('manual_reference')->label(self::field('manual_reference'))->placeholder('—'),
                TextEntry::make('refund_reference')->label(self::field('refund_reference'))->placeholder('—'),
                TextEntry::make('refunded_at')->label(self::field('refunded_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    public static function needsReview(Payment $payment): bool
    {
        return ($payment->metadata['needs_manual_review'] ?? false) === true;
    }

    private static function riyadhDayStart(string $date, int $addDays = 0): CarbonImmutable
    {
        return CarbonImmutable::parse($date, Display::TIMEZONE)->startOfDay()->addDays($addDays)->utc();
    }
}
