<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Competitions\RelationManagers;

use App\Modules\Admin\Filament\Support\AdminRelationManager;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Bidding\Models\Award;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The award, and any revoked awards before it (§6.6: a re-award is a new row).
 */
final class AwardsRelationManager extends AdminRelationManager
{
    protected static string $relationship = 'awards';

    protected static string $langKey = 'awards';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with([...AdminResource::withOrganization(), 'justificationReason']))
            ->columns([
                TextColumn::make('organization.name')->label(Lang::get('fields.winner')),
                TextColumn::make('amount_minor')->label(Lang::get('fields.amount'))
                    ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null),
                TextColumn::make('status')->label(Lang::get('fields.status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                IconColumn::make('is_leading_offer')->label(Lang::get('fields.is_leading_offer'))->boolean(),
                TextColumn::make('rank_at_award')->label(Lang::get('fields.rank')),
                IconColumn::make('reserve_met')->label(Lang::get('fields.reserve_met'))->boolean()->placeholder('—'),
                TextColumn::make('justification')->label(Lang::get('fields.justification'))->placeholder('—')->wrap()
                    ->state(static fn (Award $record): ?string => $record->justificationReason !== null
                        ? trim(($record->justificationReason->translated('name') ?? '').' '.($record->justification_text ?? ''))
                        : $record->justification_text),
                TextColumn::make('awarded_at')->label(Lang::get('fields.awarded_at'))->dateTime(Display::DATE_TIME),
                TextColumn::make('revoked_at')->label(Lang::get('fields.revoked_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextColumn::make('revoke_reason')->label(Lang::get('fields.revoke_reason'))->placeholder('—')->wrap(),
                TextColumn::make('erp_sync_status')->label(Lang::get('fields.erp_sync_status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
            ])
            ->defaultSort('awarded_at', 'desc');
    }
}
