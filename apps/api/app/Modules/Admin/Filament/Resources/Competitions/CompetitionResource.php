<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Competitions;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\Competitions\Pages\ListCompetitions;
use App\Modules\Admin\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\AwardsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\ExtensionsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\InvitationsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\OffersRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\ParticipantsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\RejectionsRelationManager;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Support\AdminScope;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Models\Competition;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * §16 Competitions: list with a status filter; view with the timeline, invitations,
 * participants, the offer ledger (issuer projection plus voids), extensions, rejections and the
 * award. Actions: extend, cancel, force close (Competitions) and void offer (Bidding).
 *
 * Release scope `core` (RELEASE_SCOPE.md §11): list and view with the timeline, invitations,
 * participants, offers and awards; Cancel and Force close only. The BAFO round, final window and
 * sponsorship rows show in `core` only on a competition that uses them.
 */
final class CompetitionResource extends AdminResource
{
    protected static ?string $model = Competition::class;

    protected static string $langKey = 'competitions';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Competitions;

    protected static ?OpsSurface $opsSurface = OpsSurface::Competitions;

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * Soft-deleted organizations stay visible (account deletion keeps their records).
     *
     * @return Builder<Competition>
     */
    public static function getEloquentQuery(): Builder
    {
        return Competition::query()->with(self::withOrganization());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->withCount(['participants', 'invitations']))
            ->columns([
                TextColumn::make('reference_no')->label(self::field('reference_no'))->searchable()->placeholder('—'),
                TextColumn::make('title')->label(self::field('title'))->searchable()->limit(60),
                TextColumn::make('organization.name')->label(self::field('issuer'))->searchable(),
                TextColumn::make('direction')->label(self::field('direction'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('format')->label(self::field('format'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('invitations_count')->label(self::field('invitations_count'))->toggleable(),
                TextColumn::make('participants_count')->label(self::field('participants_count'))->toggleable(),
                TextColumn::make('effective_close_at')->label(self::field('effective_close_at'))->dateTime(Display::DATE_TIME)->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME)->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(self::field('status'))->multiple()->options(Display::options(CompetitionStatus::class)),
                SelectFilter::make('direction')->label(self::field('direction'))->options(Display::options(Direction::class)),
                SelectFilter::make('format')->label(self::field('format'))->options(Display::options(Format::class)),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(self::field('section_summary'))->columns(4)->schema([
                TextEntry::make('reference_no')->label(self::field('reference_no'))->placeholder('—'),
                TextEntry::make('title')->label(self::field('title'))->columnSpan(3),
                TextEntry::make('organization.name')->label(self::field('issuer'))
                    ->url(static fn (Competition $record): ?string => OrganizationResource::link($record->organization)),
                TextEntry::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextEntry::make('phase')->label(self::field('phase'))->placeholder('—')
                    ->state(static fn (Competition $record): ?string => Display::enum($record->phaseAt(CarbonImmutable::now()))),
                TextEntry::make('source')->label(self::field('source'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextEntry::make('direction')->label(self::field('direction'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextEntry::make('format')->label(self::field('format'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextEntry::make('category')->label(self::field('category'))
                    ->state(static fn (Competition $record): ?string => $record->category->translated('name')),
                TextEntry::make('region')->label(self::field('region'))
                    ->state(static fn (Competition $record): ?string => $record->region->translated('name')),
            ]),
            Section::make(self::field('section_rules'))->columns(4)->collapsible()->schema([
                TextEntry::make('start_price_minor')->label(self::field('start_price'))->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null),
                TextEntry::make('reserve_price_minor')->label(self::field('reserve_price'))->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null),
                TextEntry::make('min_step')->label(self::field('min_step'))->placeholder('—')
                    ->state(static fn (Competition $record): ?string => $record->min_step_minor !== null
                        ? Display::money($record->min_step_minor)
                        : ($record->min_step_bps !== null ? rtrim(rtrim(number_format($record->min_step_bps / 100, 2, '.', ''), '0'), '.').'%' : null)),
                TextEntry::make('amount_granularity_minor')->label(self::field('granularity'))
                    ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null),
                TextEntry::make('must_beat')->label(self::field('must_beat'))->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextEntry::make('rank_visibility')->label(self::field('rank_visibility'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                IconEntry::make('show_prices')->label(self::field('show_prices'))->boolean(),
                TextEntry::make('result_publication')->label(self::field('result_publication'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextEntry::make('auto_extend')->label(self::field('auto_extend'))
                    ->state(static fn (Competition $record): string => $record->auto_extend_enabled
                        ? sprintf('%d / %d / %d', (int) $record->auto_extend_window_seconds, (int) $record->auto_extend_by_seconds, (int) $record->auto_extend_max)
                        : Display::yesNo(false)),
                TextEntry::make('final_window_minutes')->label(self::field('final_window_minutes'))->placeholder('—')
                    ->visible(static fn (Competition $record): bool => self::showsFinalWindow($record)),
                TextEntry::make('bafo_round')->label(self::field('bafo_round'))
                    ->visible(static fn (Competition $record): bool => AdminScope::visible(OpsSurface::CompetitionBafoRound) || $record->bafo_round_enabled)
                    ->state(static fn (Competition $record): string => $record->bafo_round_enabled
                        ? (string) $record->bafo_duration_minutes
                        : Display::yesNo(false)),
                TextEntry::make('min_participants')->label(self::field('min_participants')),
            ]),
            Section::make(self::field('section_timeline'))->columns(4)->collapsible()->schema(array_map(
                static fn (string $column): TextEntry => TextEntry::make($column)->label(self::field($column))
                    ->dateTime(Display::DATE_TIME)->placeholder('—')
                    ->visible(static fn (Competition $record): bool => ! str_starts_with($column, 'final_window_') || self::showsFinalWindow($record)),
                [
                    'created_at', 'published_at', 'bidding_opens_at', 'opened_at',
                    'final_window_starts_at', 'final_window_started_at', 'invitation_cutoff_at', 'scheduled_close_at',
                    'effective_close_at', 'hard_stop_at', 'closed_at', 'offers_opened_at',
                    'awarded_at', 'not_awarded_at', 'cancelled_at',
                ],
            )),
            Section::make(self::field('section_outcome'))->columns(4)->collapsible()->schema([
                TextEntry::make('extension_count')->label(self::field('extension_count')),
                TextEntry::make('leading_amount')->label(self::field('leading_amount'))->placeholder('—')
                    ->state(static fn (Competition $record): ?string => Display::money(app(VisibilityProjector::class)->issuerLeadingAmount($record))),
                TextEntry::make('liveState.accepted_offer_count')->label(self::field('offers_count'))->default(0),
                TextEntry::make('liveState.participants_with_offers')->label(self::field('participants_with_offers'))->default(0),
                TextEntry::make('cancel_reason')->label(self::field('cancel_reason'))->placeholder('—')
                    ->state(static fn (Competition $record): ?string => $record->cancelReason?->translated('name')),
                TextEntry::make('cancel_note')->label(self::field('cancel_note'))->placeholder('—'),
                TextEntry::make('not_awarded_reason')->label(self::field('not_awarded_reason'))->placeholder('—')
                    ->state(static fn (Competition $record): ?string => $record->notAwardedReason?->translated('name')),
                TextEntry::make('not_awarded_note')->label(self::field('not_awarded_note'))->placeholder('—'),
                TextEntry::make('sponsorship')->label(self::field('sponsorship'))->placeholder('—')
                    ->visible(static fn (Competition $record): bool => AdminScope::visible(OpsSurface::CompetitionSponsorship) || $record->sponsorship !== null)
                    ->state(static fn (Competition $record): ?string => $record->sponsorship !== null
                        ? Display::enum($record->sponsorship->mode).' · '.Display::enum($record->sponsorship->status).' · '.$record->sponsorship->funded_passes
                        : null),
            ]),
        ]);
    }

    /** The final pricing window rows: release scope `full`, or a competition that has a window. */
    private static function showsFinalWindow(Competition $competition): bool
    {
        return AdminScope::visible(OpsSurface::CompetitionFinalWindow) || $competition->final_window_minutes !== null;
    }

    public static function getRelations(): array
    {
        return [
            InvitationsRelationManager::class,
            ParticipantsRelationManager::class,
            OffersRelationManager::class,
            ExtensionsRelationManager::class,
            RejectionsRelationManager::class,
            AwardsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompetitions::route('/'),
            'view' => ViewCompetition::route('/{record}'),
        ];
    }
}
