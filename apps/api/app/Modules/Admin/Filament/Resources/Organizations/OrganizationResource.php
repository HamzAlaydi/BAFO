<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Organizations;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Modules\Admin\Filament\Resources\Organizations\Pages\ViewOrganization;
use App\Modules\Admin\Filament\Resources\Organizations\RelationManagers\CompetitionsRelationManager;
use App\Modules\Admin\Filament\Resources\Organizations\RelationManagers\MembersRelationManager;
use App\Modules\Admin\Filament\Resources\Organizations\RelationManagers\SubscriptionsRelationManager;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Support\AdminScope;
use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Organization;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * §16 Organizations: search and view (profile, members, subscription, competitions). The view
 * page carries the admin actions (verify, suspend, feature flags, resend the owner's OTP, grant
 * a subscription), each through the Identity or Billing Action.
 *
 * Release scope `core` (RELEASE_SCOPE.md §11) keeps the auction switch and hides the API and
 * sponsorship switches (OpsSurface::OrganizationAdvancedFeatures) everywhere on the resource.
 */
final class OrganizationResource extends AdminResource
{
    protected static ?string $model = Organization::class;

    protected static string $langKey = 'organizations';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Customers;

    protected static ?OpsSurface $opsSurface = OpsSurface::Organizations;

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Deleted organizations stay visible (status `deleted`).
     *
     * @return Builder<Organization>
     */
    public static function getEloquentQuery(): Builder
    {
        return Organization::query()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('region')->withCount('memberships'))
            ->columns([
                TextColumn::make('name')->label(self::field('name'))
                    ->searchable()->sortable()
                    ->description(static fn (Organization $record): ?string => $record->legal_name_ar),
                TextColumn::make('cr_number')->label(self::field('cr_number'))->searchable(),
                TextColumn::make('email')->label(self::field('email'))->searchable()->toggleable(),
                TextColumn::make('region')->label(self::field('region'))
                    ->state(static fn (Organization $record): ?string => $record->region?->translated('name'))
                    ->toggleable(),
                TextColumn::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                IconColumn::make('verified')->label(self::field('verified'))->boolean()
                    ->state(static fn (Organization $record): bool => $record->verified_at !== null),
                IconColumn::make('api_enabled')->label(self::field('api_enabled'))->boolean()->toggleable()
                    ->visible(static fn (): bool => self::showsAdvancedFeatures()),
                IconColumn::make('auction_enabled')->label(self::field('auction_enabled'))->boolean()->toggleable(),
                IconColumn::make('sponsorship_enabled')->label(self::field('sponsorship_enabled'))->boolean()->toggleable()
                    ->visible(static fn (): bool => self::showsAdvancedFeatures()),
                TextColumn::make('memberships_count')->label(self::field('members_count'))->toggleable(),
                TextColumn::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME)->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(self::field('status'))
                    ->options(Display::options(OrganizationStatus::class)),
                TernaryFilter::make('verified_at')->label(self::field('verified'))->nullable(),
                TernaryFilter::make('api_enabled')->label(self::field('api_enabled'))
                    ->visible(static fn (): bool => self::showsAdvancedFeatures()),
                TernaryFilter::make('auction_enabled')->label(self::field('auction_enabled')),
                TernaryFilter::make('sponsorship_enabled')->label(self::field('sponsorship_enabled'))
                    ->visible(static fn (): bool => self::showsAdvancedFeatures()),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(self::field('section_profile'))->columns(3)->schema([
                TextEntry::make('name')->label(self::field('name')),
                TextEntry::make('legal_name_ar')->label(self::field('legal_name_ar'))->placeholder('—'),
                TextEntry::make('legal_name_en')->label(self::field('legal_name_en'))->placeholder('—'),
                TextEntry::make('cr_number')->label(self::field('cr_number')),
                IconEntry::make('vat_registered')->label(self::field('vat_registered'))->boolean(),
                TextEntry::make('vat_number')->label(self::field('vat_number'))->placeholder('—'),
                TextEntry::make('email')->label(self::field('email')),
                TextEntry::make('phone')->label(self::field('phone')),
                TextEntry::make('website')->label(self::field('website'))->placeholder('—'),
                TextEntry::make('region')->label(self::field('region'))
                    ->state(static fn (Organization $record): ?string => $record->region?->translated('name')),
                TextEntry::make('city')->label(self::field('city')),
                TextEntry::make('address')->label(self::field('address'))->placeholder('—')
                    ->state(static fn (Organization $record): ?string => self::address($record)),
                TextEntry::make('categories')->label(self::field('categories'))->placeholder('—')->badge()
                    ->state(static fn (Organization $record): array => $record->categories
                        ->map(static fn (Category $category): string => $category->translated('name') ?? $category->code)
                        ->all()),
                IconEntry::make('visible_in_suggestions')->label(self::field('visible_in_suggestions'))->boolean(),
                IconEntry::make('billing_profile_complete')->label(self::field('billing_profile_complete'))->boolean()
                    ->state(static fn (Organization $record): bool => $record->isBillingProfileComplete()),
                TextEntry::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME),
            ]),
            Section::make(self::field('section_status'))->columns(3)->schema([
                TextEntry::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextEntry::make('verified_at')->label(self::field('verified_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('suspended_at')->label(self::field('suspended_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('suspension_reason')->label(self::field('suspension_reason'))->placeholder('—')->columnSpanFull(),
                IconEntry::make('api_enabled')->label(self::field('api_enabled'))->boolean()
                    ->visible(static fn (): bool => self::showsAdvancedFeatures()),
                IconEntry::make('auction_enabled')->label(self::field('auction_enabled'))->boolean(),
                IconEntry::make('sponsorship_enabled')->label(self::field('sponsorship_enabled'))->boolean()
                    ->visible(static fn (): bool => self::showsAdvancedFeatures()),
                TextEntry::make('trial_used_at')->label(self::field('trial_used_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
            ]),
            Section::make(self::field('section_subscription'))->columns(3)->schema([
                TextEntry::make('subscription_plan')->label(self::field('plan'))->placeholder(self::field('no_subscription'))
                    ->state(static fn (Organization $record): ?string => ($subscription = self::currentSubscription($record)) !== null
                        ? Display::translated($subscription->plan->name)
                        : null),
                TextEntry::make('subscription_source')->label(self::field('source'))->placeholder('—')
                    ->state(static fn (Organization $record): ?string => Display::enum(self::currentSubscription($record)?->source)),
                TextEntry::make('subscription_seats')->label(self::field('seats'))->placeholder('—')
                    ->state(static fn (Organization $record): ?int => self::currentSubscription($record)?->seats),
                TextEntry::make('subscription_ends_at')->label(self::field('ends_at'))->placeholder('—')
                    ->state(static fn (Organization $record): ?string => Display::dateTime(self::currentSubscription($record)?->ends_at)),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
            SubscriptionsRelationManager::class,
            CompetitionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'view' => ViewOrganization::route('/{record}'),
        ];
    }

    /** The API and sponsorship switches (release scope `full`). */
    public static function showsAdvancedFeatures(): bool
    {
        return AdminScope::visible(OpsSurface::OrganizationAdvancedFeatures);
    }

    /**
     * The view page of an organization, or null (a relation that did not load).
     */
    public static function link(mixed $organization): ?string
    {
        return $organization instanceof Organization ? self::getUrl('view', ['record' => $organization]) : null;
    }

    private static function currentSubscription(Organization $organization): ?Subscription
    {
        return app(AccessPolicy::class)->activeSubscription($organization);
    }

    private static function address(Organization $organization): ?string
    {
        $parts = array_filter([
            $organization->address_building_number,
            $organization->address_street,
            $organization->address_district,
            $organization->address_postal_code,
            $organization->address_additional_number,
            $organization->address_short,
        ], static fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode('، ', $parts);
    }
}
