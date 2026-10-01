<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Organizations\RelationManagers;

use App\Modules\Admin\Filament\Resources\Competitions\CompetitionResource;
use App\Modules\Admin\Filament\Support\AdminRelationManager;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Competitions\Models\Competition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The competitions the organization issued, newest first.
 */
final class CompetitionsRelationManager extends AdminRelationManager
{
    protected static string $relationship = 'competitions';

    protected static string $langKey = 'competitions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_no')->label(Lang::get('fields.reference_no'))->placeholder('—'),
                TextColumn::make('title')->label(Lang::get('fields.title'))->limit(60)->searchable(),
                TextColumn::make('direction')->label(Lang::get('fields.direction'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('status')->label(Lang::get('fields.status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('effective_close_at')->label(Lang::get('fields.effective_close_at'))
                    ->dateTime(Display::DATE_TIME)->placeholder('—'),
            ])
            ->recordUrl(static fn (Competition $record): string => CompetitionResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }
}
