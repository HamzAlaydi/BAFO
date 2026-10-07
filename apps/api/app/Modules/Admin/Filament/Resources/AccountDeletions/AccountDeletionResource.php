<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\AccountDeletions;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\AccountDeletions\Pages\ListAccountDeletions;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Identity\Enums\DeletionScope;
use App\Modules\Identity\Models\AccountDeletionRequest;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * §16 Account deletions: the read-only queue (pending, completed, cancelled). Identity's
 * `identity:execute-account-deletions` executes the due requests.
 */
final class AccountDeletionResource extends AdminResource
{
    protected static ?string $model = AccountDeletionRequest::class;

    protected static string $langKey = 'account_deletions';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Customers;

    protected static ?OpsSurface $opsSurface = OpsSurface::AccountDeletions;

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserMinus;

    protected static ?string $slug = 'account-deletions';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with([
                'user' => static fn ($user) => $user->withTrashed(),
                'organization' => static fn ($organization) => $organization->withTrashed(),
            ]))
            ->columns([
                TextColumn::make('user.name')->label(self::field('user'))
                    ->description(static fn (AccountDeletionRequest $record): ?string => $record->user?->email),
                TextColumn::make('organization.name')->label(self::field('organization')),
                TextColumn::make('scope')->label(self::field('scope'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('reason')->label(self::field('reason'))->placeholder('—')->limit(40)->toggleable(),
                TextColumn::make('created_at')->label(self::field('requested_at'))->dateTime(Display::DATE_TIME)->sortable(),
                TextColumn::make('scheduled_for')->label(self::field('scheduled_for'))->dateTime(Display::DATE_TIME)->sortable(),
                TextColumn::make('completed_at')->label(self::field('completed_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextColumn::make('cancelled_at')->label(self::field('cancelled_at'))->dateTime(Display::DATE_TIME)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('scope')->label(self::field('scope'))->options(Display::options(DeletionScope::class)),
            ])
            ->defaultSort('scheduled_for');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountDeletions::route('/'),
        ];
    }
}
