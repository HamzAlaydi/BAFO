<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\Admin\Filament\Resources\Competitions\CompetitionResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * §16 Dashboard: the live-events table (competitions currently accepting offers, soonest close
 * first).
 */
final class LiveCompetitionsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(Lang::get('dashboard.live.heading'))
            ->query(static fn (): Builder => Competition::query()
                ->where('status', CompetitionStatus::Live->value)
                ->with(['organization', 'liveState'])
                ->withCount('participants'))
            ->columns([
                TextColumn::make('reference_no')->label(Lang::get('fields.reference_no')),
                TextColumn::make('title')->label(Lang::get('fields.title'))->limit(60),
                TextColumn::make('organization.name')->label(Lang::get('fields.issuer')),
                TextColumn::make('direction')->label(Lang::get('fields.direction'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('format')->label(Lang::get('fields.format'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('phase')->label(Lang::get('fields.phase'))->badge()
                    ->state(static fn (Competition $record): ?string => Display::enum($record->phaseAt(CarbonImmutable::now()))),
                TextColumn::make('participants_count')->label(Lang::get('fields.participants_count')),
                TextColumn::make('liveState.accepted_offer_count')->label(Lang::get('fields.offers_count'))->default(0),
                TextColumn::make('effective_close_at')->label(Lang::get('fields.effective_close_at'))
                    ->dateTime(Display::DATE_TIME)->sortable(),
            ])
            ->defaultSort('effective_close_at')
            ->recordUrl(static fn (Competition $record): string => CompetitionResource::getUrl('view', ['record' => $record]))
            ->paginated([10, 25, 50]);
    }
}
