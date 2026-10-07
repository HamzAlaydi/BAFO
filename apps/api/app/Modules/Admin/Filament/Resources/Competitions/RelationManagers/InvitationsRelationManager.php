<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Competitions\RelationManagers;

use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Support\AdminRelationManager;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Admin\Support\AdminScope;
use App\Modules\Billing\Actions\GrantSponsoredPass;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Competitions\Actions\RevokeInvitation;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The competition's invitations. Revoke → Competitions `RevokeInvitation` (reason `admin`);
 * Grant pass → Billing `GrantSponsoredPass` (§16 Sponsorships, for one invitation). Release scope
 * `core` shows the list only: Revoke, Grant pass and the sponsored columns are `full`
 * (OpsSurface::CompetitionRevokeInvitation, CompetitionSponsorship; RELEASE_SCOPE.md §11).
 */
final class InvitationsRelationManager extends AdminRelationManager
{
    protected static string $relationship = 'invitations';

    protected static string $langKey = 'invitations';

    /** The competition's sponsorship, read once per request (false: not read yet). */
    protected CompetitionSponsorship|false|null $sponsorship = false;

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query
                ->with(AdminResource::withOrganization())
                ->withExists(['sponsoredPasses as has_live_pass' => static fn (Builder $passes) => $passes->whereIn('status', PassStatus::live())]))
            ->columns([
                TextColumn::make('email')->label(Lang::get('fields.email'))->searchable(),
                TextColumn::make('name')->label(Lang::get('fields.name'))->placeholder('—'),
                TextColumn::make('organization.name')->label(Lang::get('fields.organization'))->placeholder('—'),
                TextColumn::make('status')->label(Lang::get('fields.status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                IconColumn::make('sponsored_requested')->label(Lang::get('fields.sponsored_requested'))->boolean()
                    ->visible(static fn (): bool => AdminScope::visible(OpsSurface::CompetitionSponsorship)),
                IconColumn::make('has_live_pass')->label(Lang::get('fields.sponsored_pass'))->boolean()
                    ->visible(static fn (): bool => AdminScope::visible(OpsSurface::CompetitionSponsorship)),
                TextColumn::make('sent_at')->label(Lang::get('fields.sent_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextColumn::make('joined_at')->label(Lang::get('fields.joined_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextColumn::make('revoke_reason')->label(Lang::get('fields.revoke_reason'))->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label(Lang::get('fields.status'))->options(Display::options(InvitationStatus::class)),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(Lang::get('resources.competitions.actions.revoke_invitation'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(static fn (Invitation $record): bool => AdminScope::visible(OpsSurface::CompetitionRevokeInvitation)
                        && in_array($record->status, [InvitationStatus::Sent, InvitationStatus::Viewed], true))
                    ->requiresConfirmation()
                    ->action(static fn (Invitation $record) => ModuleAction::run(
                        static fn () => app(RevokeInvitation::class)->handle($record, AdminActor::current()),
                        Lang::get('resources.competitions.notifications.invitation_revoked'),
                    )),
                Action::make('grantPass')
                    ->label(Lang::get('resources.sponsorships.actions.grant_pass'))
                    ->icon(Heroicon::OutlinedTicket)
                    ->visible(fn (Invitation $record): bool => $this->canGrantPass($record))
                    ->requiresConfirmation()
                    ->action(static fn (Invitation $record) => ModuleAction::run(
                        static fn () => app(GrantSponsoredPass::class)->handle($record, AdminActor::current()),
                        Lang::get('resources.sponsorships.notifications.pass_granted'),
                    )),
            ])
            ->defaultSort('id');
    }

    /**
     * An open invitation without a live pass, on a competition whose sponsorship is not settled
     * (the rule GrantSponsoredPass enforces).
     */
    private function canGrantPass(Invitation $invitation): bool
    {
        if (! AdminScope::visible(OpsSurface::CompetitionSponsorship)) {
            return false;
        }

        $sponsorship = $this->sponsorship();

        return $sponsorship instanceof CompetitionSponsorship
            && $sponsorship->status !== SponsorshipStatus::Settled
            && in_array($invitation->status, [InvitationStatus::Draft, InvitationStatus::Sent, InvitationStatus::Viewed], true)
            && ! (bool) $invitation->getAttribute('has_live_pass');
    }

    private function sponsorship(): ?CompetitionSponsorship
    {
        if ($this->sponsorship === false) {
            $this->sponsorship = CompetitionSponsorship::query()->where('competition_id', $this->getOwnerRecord()->getKey())->first();
        }

        return $this->sponsorship;
    }
}
