<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Competitions\RelationManagers;

use App\Modules\Admin\Filament\Support\AdminRelationManager;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Fields;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Bidding\Actions\VoidOffer;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The offer ledger in the issuer projection, plus voids (§16). Void offer → Bidding `VoidOffer`
 * (a reason of kind `void_offer`, with a note when it requires one). The ledger rows never
 * change; a void adds an `offer_voids` row and re-ranks (§7.13).
 */
final class OffersRelationManager extends AdminRelationManager
{
    protected static string $relationship = 'offers';

    protected static string $langKey = 'offers';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with(['participant', ...AdminResource::withOrganization('participant.organization'), 'void.reason']))
            ->columns([
                TextColumn::make('seq')->label(Lang::get('fields.seq'))->sortable(),
                TextColumn::make('participant')->label(Lang::get('fields.participant'))
                    ->state(static fn (Offer $record): string => Lang::get('common.participant_alias', ['n' => $record->participant->alias_no])
                        .' · '.($record->participant->organization->name ?? '—')),
                TextColumn::make('stage')->label(Lang::get('fields.stage'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('amount')->label(Lang::get('fields.amount'))
                    ->state(fn (Offer $record): string => ($amount = app(VisibilityProjector::class)->issuerOfferAmount($record, $this->competition())) !== null
                        ? (string) Display::money($amount)
                        : Lang::get('common.sealed')),
                TextColumn::make('accepted_at')->label(Lang::get('fields.accepted_at'))->dateTime(Display::PRECISE),
                TextColumn::make('channel')->label(Lang::get('fields.channel'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                IconColumn::make('voided')->label(Lang::get('fields.voided'))->boolean()
                    ->state(static fn (Offer $record): bool => $record->void !== null),
                TextColumn::make('void_reason')->label(Lang::get('fields.void_reason'))->placeholder('—')
                    ->state(static fn (Offer $record): ?string => $record->void !== null
                        ? trim(($record->void->reason->translated('name') ?? '').' '.($record->void->note ?? ''))
                        : null),
                TextColumn::make('hash')->label(Lang::get('fields.hash'))->limit(12)->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('void')
                    ->label(Lang::get('resources.competitions.actions.void_offer'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (Offer $record): bool => $record->void === null && ! in_array($this->competition()->status, [
                        CompetitionStatus::Awarded, CompetitionStatus::NotAwarded, CompetitionStatus::Cancelled,
                    ], true))
                    ->requiresConfirmation()
                    ->modalDescription(Lang::get('resources.competitions.actions.void_offer_help'))
                    ->schema(Fields::closeReason(CloseReasonKind::VoidOffer))
                    ->action(static function (Offer $record, array $data): void {
                        $reason = CloseReason::query()->findOrFail($data['reason_id']);
                        $note = is_string($data['note'] ?? null) && trim($data['note']) !== '' ? trim($data['note']) : null;

                        ModuleAction::run(
                            static fn () => app(VoidOffer::class)->handle($record, $reason, $note, AdminActor::current()),
                            Lang::get('resources.competitions.notifications.offer_voided'),
                        );
                    }),
            ])
            ->defaultSort('seq', 'desc');
    }

    private function competition(): Competition
    {
        /** @var Competition $competition */
        $competition = $this->getOwnerRecord();

        return $competition;
    }
}
