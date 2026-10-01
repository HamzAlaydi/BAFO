<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Competitions\RelationManagers;

use App\Modules\Admin\Filament\Support\AdminRelationManager;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The competition's extensions: automatic (anti-sniping), manual (issuer) and admin.
 */
final class ExtensionsRelationManager extends AdminRelationManager
{
    protected static string $relationship = 'extensions';

    protected static string $langKey = 'extensions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->label(Lang::get('fields.kind'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('previous_close_at')->label(Lang::get('fields.previous_close_at'))->dateTime(Display::PRECISE),
                TextColumn::make('new_close_at')->label(Lang::get('fields.new_close_at'))->dateTime(Display::PRECISE),
                TextColumn::make('reason')->label(Lang::get('fields.reason'))->placeholder('—')->wrap(),
                TextColumn::make('created_at')->label(Lang::get('fields.created_at'))->dateTime(Display::DATE_TIME),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
