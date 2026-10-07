<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Competitions\RelationManagers;

use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Support\AdminRelationManager;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Bidding\Models\OfferRejection;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Models\Competition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rejected offer attempts, kept for disputes (§5.6 `offer_rejections`). Amounts follow the issuer
 * projection. Release scope `full` only (RELEASE_SCOPE.md §11).
 */
final class RejectionsRelationManager extends AdminRelationManager
{
    protected static string $relationship = 'offerRejections';

    protected static string $langKey = 'rejections';

    protected static ?OpsSurface $opsSurface = OpsSurface::CompetitionRejections;

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with(['participant', ...AdminResource::withOrganization('participant.organization')]))
            ->columns([
                TextColumn::make('received_at')->label(Lang::get('fields.received_at'))->dateTime(Display::PRECISE),
                TextColumn::make('participant')->label(Lang::get('fields.participant'))->placeholder('—')
                    ->state(static fn (OfferRejection $record): ?string => $record->participant !== null
                        ? Lang::get('common.participant_alias', ['n' => $record->participant->alias_no]).' · '.($record->participant->organization->name ?? '—')
                        : null),
                TextColumn::make('amount')->label(Lang::get('fields.amount'))->placeholder('—')
                    ->state(fn (OfferRejection $record): ?string => $record->amount_minor === null
                        ? null
                        : ($this->amountsHidden() ? Lang::get('common.sealed') : Display::money($record->amount_minor))),
                TextColumn::make('code')->label(Lang::get('fields.code'))->badge()->color('danger'),
                TextColumn::make('stage')->label(Lang::get('fields.stage'))->placeholder('—'),
                TextColumn::make('channel')->label(Lang::get('fields.channel'))->placeholder('—'),
                TextColumn::make('ip')->label(Lang::get('fields.ip'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function amountsHidden(): bool
    {
        /** @var Competition $competition */
        $competition = $this->getOwnerRecord();

        return app(VisibilityProjector::class)->amountsHiddenFromIssuer($competition);
    }
}
