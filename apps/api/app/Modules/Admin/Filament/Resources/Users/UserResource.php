<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Admin\Filament\Resources\Users\Pages\ViewUser;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * §16 Users: search and view the organization users; deactivate / reactivate the membership
 * through Identity's `ChangeMembershipStatus`.
 */
final class UserResource extends AdminResource
{
    protected static ?string $model = User::class;

    protected static string $langKey = 'users';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Customers;

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Anonymised (deleted) users stay visible.
     *
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return User::query()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['membership', ...self::withOrganization('membership.organization')]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(self::field('name'))->searchable()->sortable(),
                TextColumn::make('email')->label(self::field('email'))->searchable(),
                TextColumn::make('membership.organization.name')->label(self::field('organization'))->placeholder('—'),
                TextColumn::make('membership.role')->label(self::field('role'))->badge()->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('membership.status')->label(self::field('membership_status'))->badge()->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                IconColumn::make('email_verified')->label(self::field('email_verified'))->boolean()
                    ->state(static fn (User $record): bool => $record->email_verified_at !== null),
                TextColumn::make('last_login_at')->label(self::field('last_login_at'))->dateTime(Display::DATE_TIME)->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME)->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(self::field('status'))->options(Display::options(UserStatus::class)),
                SelectFilter::make('membership_status')->label(self::field('membership_status'))
                    ->options(Display::options(MembershipStatus::class))
                    ->query(static fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('membership', static fn (Builder $membership) => $membership->where('status', $data['value']))
                        : $query),
                SelectFilter::make('role')->label(self::field('role'))
                    ->options(Display::options(OrgRole::class))
                    ->query(static fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('membership', static fn (Builder $membership) => $membership->where('role', $data['value']))
                        : $query),
            ])
            ->recordActions([
                ViewAction::make(),
                ...MembershipStatusActions::make(static fn (Model $record): ?Membership => $record instanceof User ? $record->membership : null),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(self::field('section_profile'))->columns(3)->schema([
                TextEntry::make('name')->label(self::field('name')),
                TextEntry::make('email')->label(self::field('email')),
                TextEntry::make('phone')->label(self::field('phone'))->placeholder('—'),
                TextEntry::make('locale')->label(self::field('locale')),
                TextEntry::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextEntry::make('email_verified_at')->label(self::field('email_verified_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('last_login_at')->label(self::field('last_login_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME),
            ]),
            Section::make(self::field('section_membership'))->columns(3)->schema([
                TextEntry::make('membership.organization.name')->label(self::field('organization'))->placeholder('—')
                    ->url(static fn (User $record): ?string => OrganizationResource::link($record->membership?->organization)),
                TextEntry::make('membership.role')->label(self::field('role'))->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextEntry::make('membership.status')->label(self::field('membership_status'))->badge()->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                IconEntry::make('membership.can_award')->label(self::field('can_award'))->boolean(),
                IconEntry::make('membership.can_purchase')->label(self::field('can_purchase'))->boolean(),
                TextEntry::make('membership.joined_at')->label(self::field('joined_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
