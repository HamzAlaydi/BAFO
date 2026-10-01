<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Competitions\RelationManagers;

use App\Modules\Admin\Filament\Support\AdminRelationManager;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The joined participants with their standing, in the issuer projection (amounts hidden while a
 * sealed competition is not unsealed, §7.9).
 */
final class ParticipantsRelationManager extends AdminRelationManager
{
    protected static string $relationship = 'participants';

    protected static string $langKey = 'participants';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with([...AdminResource::withOrganization(), 'standing']))
            ->columns([
                TextColumn::make('alias_no')->label(Lang::get('fields.alias'))
                    ->formatStateUsing(static fn (mixed $state): string => Lang::get('common.participant_alias', ['n' => (int) $state])),
                TextColumn::make('organization.name')->label(Lang::get('fields.organization')),
                TextColumn::make('entitlement_source')->label(Lang::get('fields.entitlement_source'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('standing.rank')->label(Lang::get('fields.rank'))->placeholder('—'),
                IconColumn::make('standing.is_leader')->label(Lang::get('fields.is_leader'))->boolean(),
                TextColumn::make('current_amount')->label(Lang::get('fields.current_amount'))->placeholder('—')
                    ->state(fn (Participant $record): ?string => $this->amountsHidden()
                        ? Lang::get('common.sealed')
                        : Display::money($record->standing?->current_amount_minor)),
                TextColumn::make('standing.offers_count')->label(Lang::get('fields.offers_count'))->default(0),
                TextColumn::make('created_at')->label(Lang::get('fields.joined_at'))->dateTime(Display::DATE_TIME),
            ])
            ->defaultSort('alias_no');
    }

    private function amountsHidden(): bool
    {
        /** @var Competition $competition */
        $competition = $this->getOwnerRecord();

        return app(VisibilityProjector::class)->amountsHiddenFromIssuer($competition);
    }
}
