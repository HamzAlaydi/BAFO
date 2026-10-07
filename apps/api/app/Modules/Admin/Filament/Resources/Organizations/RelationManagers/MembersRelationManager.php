<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Organizations\RelationManagers;

use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\Users\MembershipStatusActions;
use App\Modules\Admin\Filament\Resources\Users\UserResource;
use App\Modules\Admin\Filament\Support\AdminRelationManager;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Support\AdminScope;
use App\Modules\Identity\Models\Membership;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The organization's members, with deactivate / reactivate (§16 Users). The award / purchase
 * permission columns are OpsSurface::TeamPermissions (`full`, RELEASE_SCOPE.md §11).
 */
final class MembersRelationManager extends AdminRelationManager
{
    protected static string $relationship = 'memberships';

    protected static string $langKey = 'members';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with(['user' => static fn ($user) => $user->withTrashed()]))
            ->columns([
                TextColumn::make('user.name')->label(Lang::get('fields.name')),
                TextColumn::make('user.email')->label(Lang::get('fields.email')),
                TextColumn::make('role')->label(Lang::get('fields.role'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                IconColumn::make('can_award')->label(Lang::get('fields.can_award'))->boolean()
                    ->visible(static fn (): bool => AdminScope::visible(OpsSurface::TeamPermissions)),
                IconColumn::make('can_purchase')->label(Lang::get('fields.can_purchase'))->boolean()
                    ->visible(static fn (): bool => AdminScope::visible(OpsSurface::TeamPermissions)),
                TextColumn::make('status')->label(Lang::get('fields.status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('joined_at')->label(Lang::get('fields.joined_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
            ])
            ->recordUrl(static fn (Membership $record): ?string => $record->user !== null
                ? UserResource::getUrl('view', ['record' => $record->user])
                : null)
            ->recordActions(MembershipStatusActions::make(static fn (Model $record): ?Membership => $record instanceof Membership ? $record : null))
            ->defaultSort('id');
    }
}
