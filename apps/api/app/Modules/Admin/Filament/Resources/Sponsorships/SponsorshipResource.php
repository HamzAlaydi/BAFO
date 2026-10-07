<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Sponsorships;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\Competitions\CompetitionResource;
use App\Modules\Admin\Filament\Resources\Sponsorships\Pages\ListSponsorships;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Admin\Support\PlatformMetrics;
use App\Modules\Billing\Actions\GrantSponsoredPass;
use App\Modules\Billing\Actions\IssueSponsorshipVoucher;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * §16 Sponsorships (R4): list with the pass counters. Issue voucher → Billing
 * `IssueSponsorshipVoucher` (settled, unused passes, no voucher yet); Grant pass (for an
 * invitation) → `GrantSponsoredPass`.
 */
final class SponsorshipResource extends AdminResource
{
    protected static ?string $model = CompetitionSponsorship::class;

    protected static string $langKey = 'sponsorships';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Billing;

    protected static ?OpsSurface $opsSurface = OpsSurface::Sponsorships;

    protected static ?int $navigationSort = 60;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    /**
     * Soft-deleted organizations stay visible (account deletion keeps their records).
     *
     * @return Builder<CompetitionSponsorship>
     */
    public static function getEloquentQuery(): Builder
    {
        return CompetitionSponsorship::query()->with(self::withOrganization());
    }

    public static function table(Table $table): Table
    {
        $count = static fn (PassStatus $status): Closure => static fn (Builder $passes): Builder => $passes->where('status', $status->value);

        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query
                ->with(['competition', 'voucherCoupon'])
                ->withCount([
                    'passes as reserved_passes' => $count(PassStatus::Reserved),
                    'passes as joined_passes' => $count(PassStatus::Joined),
                    'passes as released_passes' => $count(PassStatus::Released),
                    'passes as pending_passes' => $count(PassStatus::Pending),
                ]))
            ->columns([
                TextColumn::make('competition.reference_no')->label(self::field('competition'))->placeholder('—')
                    ->description(static fn (CompetitionSponsorship $record): string => (string) $record->competition->title)
                    ->url(static fn (CompetitionSponsorship $record): string => CompetitionResource::getUrl('view', ['record' => $record->competition])),
                TextColumn::make('organization.name')->label(self::field('sponsor'))->searchable(),
                TextColumn::make('mode')->label(self::field('mode'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('unit_price_minor')->label(self::field('unit_price'))
                    ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null),
                TextColumn::make('max_passes')->label(self::field('max_passes'))->placeholder('—'),
                TextColumn::make('funded_passes')->label(self::field('funded_passes')),
                TextColumn::make('pending_passes')->label(self::field('pending_passes')),
                TextColumn::make('reserved_passes')->label(self::field('reserved_passes')),
                TextColumn::make('joined_passes')->label(self::field('joined_passes')),
                TextColumn::make('released_passes')->label(self::field('released_passes')),
                TextColumn::make('unused_count')->label(self::field('unused_passes'))->placeholder('—'),
                TextColumn::make('voucherCoupon.code')->label(self::field('voucher'))->placeholder('—')->fontFamily('mono'),
                TextColumn::make('settled_at')->label(self::field('settled_at'))->dateTime(Display::DATE_TIME)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(self::field('status'))->options(Display::options(SponsorshipStatus::class)),
                SelectFilter::make('mode')->label(self::field('mode'))->options(Display::options(SponsorshipMode::class)),
                Filter::make('awaiting_voucher')->label(self::field('awaiting_voucher'))
                    ->query(static fn (Builder $query): Builder => $query->whereIn('id', PlatformMetrics::awaitingVoucherQuery()->select('id'))),
            ])
            ->recordActions([
                Action::make('issueVoucher')
                    ->label(Lang::get('resources.sponsorships.actions.issue_voucher'))
                    ->icon(Heroicon::OutlinedGift)
                    ->color('success')
                    ->visible(static fn (CompetitionSponsorship $record): bool => $record->status === SponsorshipStatus::Settled
                        && (int) $record->unused_count > 0
                        && $record->voucher_coupon_id === null)
                    ->requiresConfirmation()
                    ->modalDescription(static fn (CompetitionSponsorship $record): string => Lang::get('resources.sponsorships.actions.issue_voucher_help', [
                        'amount' => (string) Display::money((int) $record->unused_count * $record->unit_price_minor),
                    ]))
                    ->schema([
                        Textarea::make('reason')->label(Lang::get('fields.reason'))->maxLength(500),
                    ])
                    ->action(static fn (CompetitionSponsorship $record, array $data) => ModuleAction::run(
                        static fn () => app(IssueSponsorshipVoucher::class)->handle(
                            $record,
                            AdminActor::current(),
                            is_string($data['reason'] ?? null) && trim($data['reason']) !== '' ? trim($data['reason']) : null,
                        ),
                        Lang::get('resources.sponsorships.notifications.voucher_issued'),
                    )),
                Action::make('grantPass')
                    ->label(Lang::get('resources.sponsorships.actions.grant_pass'))
                    ->icon(Heroicon::OutlinedTicket)
                    ->visible(static fn (CompetitionSponsorship $record): bool => $record->status !== SponsorshipStatus::Settled)
                    ->schema(static fn (CompetitionSponsorship $record): array => [
                        Select::make('invitation_id')
                            ->label(Lang::get('fields.invitation'))
                            ->required()
                            ->searchable()
                            ->options(self::grantableInvitations($record)),
                    ])
                    ->action(static function (CompetitionSponsorship $record, array $data): void {
                        $invitation = Invitation::query()
                            ->where('competition_id', $record->competition_id)
                            ->findOrFail($data['invitation_id']);

                        ModuleAction::run(
                            static fn () => app(GrantSponsoredPass::class)->handle($invitation, AdminActor::current()),
                            Lang::get('resources.sponsorships.notifications.pass_granted'),
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Open invitations of the sponsored competition without a live pass.
     *
     * @return array<int, string>
     */
    public static function grantableInvitations(CompetitionSponsorship $sponsorship): array
    {
        return Invitation::query()
            ->where('competition_id', $sponsorship->competition_id)
            ->whereIn('status', [InvitationStatus::Draft->value, InvitationStatus::Sent->value, InvitationStatus::Viewed->value])
            ->whereDoesntHave('sponsoredPasses', static fn (Builder $passes): Builder => $passes->whereIn('status', PassStatus::live()))
            ->with(self::withOrganization())
            ->orderBy('email')
            ->get()
            ->mapWithKeys(static fn (Invitation $invitation): array => [
                $invitation->id => $invitation->email.($invitation->organization !== null ? ' · '.$invitation->organization->name : ''),
            ])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSponsorships::route('/'),
        ];
    }
}
