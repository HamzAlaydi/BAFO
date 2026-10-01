<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Organizations\RelationManagers;

use App\Modules\Admin\Filament\Support\AdminRelationManager;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Billing\Models\Subscription;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The organization's subscriptions, newest first.
 */
final class SubscriptionsRelationManager extends AdminRelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static string $langKey = 'subscriptions';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('plan'))
            ->columns([
                TextColumn::make('plan')->label(Lang::get('fields.plan'))
                    ->state(static fn (Subscription $record): ?string => Display::translated($record->plan->name)),
                TextColumn::make('source')->label(Lang::get('fields.source'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('status')->label(Lang::get('fields.status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('seats')->label(Lang::get('fields.seats')),
                TextColumn::make('starts_at')->label(Lang::get('fields.starts_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextColumn::make('ends_at')->label(Lang::get('fields.ends_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextColumn::make('total_minor')->label(Lang::get('fields.total'))->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
