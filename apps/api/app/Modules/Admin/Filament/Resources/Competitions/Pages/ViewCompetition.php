<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Competitions\Pages;

use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\Competitions\CompetitionResource;
use App\Modules\Admin\Filament\Support\Fields;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Admin\Support\AdminScope;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Actions\CancelCompetition;
use App\Modules\Competitions\Actions\ExtendCompetition;
use App\Modules\Competitions\Actions\ForceCloseCompetition;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * A competition with the §16 admin actions: Extend → `ExtendCompetition` (kind `admin`),
 * Cancel → `CancelCompetition`, Force close → `ForceCloseCompetition`, all with
 * `Actor::forAdmin()`. Voiding an offer is on the offer ledger below. Release scope `core` keeps
 * Cancel and Force close; Extend is OpsSurface::CompetitionExtend (`full`, RELEASE_SCOPE.md §11).
 */
final class ViewCompetition extends ViewRecord
{
    protected static string $resource = CompetitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('extend')
                ->label(Lang::get('resources.competitions.actions.extend'))
                ->icon(Heroicon::OutlinedClock)
                ->visible(fn (): bool => AdminScope::visible(OpsSurface::CompetitionExtend)
                    && $this->competition()->status === CompetitionStatus::Live)
                ->fillForm(fn (): array => [
                    'new_close_at' => $this->competition()->effective_close_at?->addMinutes(30)->toDateTimeString(),
                ])
                ->schema([
                    DateTimePicker::make('new_close_at')
                        ->label(Lang::get('fields.new_close_at'))
                        ->required()
                        ->seconds(false),
                    Textarea::make('reason')
                        ->label(Lang::get('fields.reason'))
                        ->required()
                        ->minLength(5)
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    ModuleAction::run(
                        fn () => app(ExtendCompetition::class)->handle(
                            $this->competition(),
                            CarbonImmutable::parse((string) $data['new_close_at'], 'UTC'),
                            trim((string) $data['reason']),
                            AdminActor::current(),
                        ),
                        Lang::get('resources.competitions.notifications.extended'),
                    );
                    $this->getRecord()->refresh();
                }),
            Action::make('forceClose')
                ->label(Lang::get('resources.competitions.actions.force_close'))
                ->icon(Heroicon::OutlinedStopCircle)
                ->color('warning')
                ->visible(fn (): bool => $this->competition()->status === CompetitionStatus::Live)
                ->requiresConfirmation()
                ->modalDescription(Lang::get('resources.competitions.actions.force_close_help'))
                ->schema([
                    Textarea::make('reason')
                        ->label(Lang::get('fields.reason'))
                        ->required()
                        ->minLength(5)
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    ModuleAction::run(
                        fn () => app(ForceCloseCompetition::class)->handle($this->competition(), trim((string) $data['reason']), AdminActor::current()),
                        Lang::get('resources.competitions.notifications.force_closed'),
                    );
                    $this->getRecord()->refresh();
                }),
            Action::make('cancel')
                ->label(Lang::get('resources.competitions.actions.cancel'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => in_array($this->competition()->status, [
                    CompetitionStatus::Scheduled, CompetitionStatus::Live, CompetitionStatus::BafoRound,
                ], true))
                ->requiresConfirmation()
                ->schema(Fields::closeReason(CloseReasonKind::Cancel))
                ->action(function (array $data): void {
                    $reason = CloseReason::query()->findOrFail($data['reason_id']);
                    $note = is_string($data['note'] ?? null) ? $data['note'] : null;

                    ModuleAction::run(
                        fn () => app(CancelCompetition::class)->handle($this->competition(), $reason, $note, AdminActor::current()),
                        Lang::get('resources.competitions.notifications.cancelled'),
                    );
                    $this->getRecord()->refresh();
                }),
        ];
    }

    private function competition(): Competition
    {
        /** @var Competition $competition */
        $competition = $this->getRecord();

        return $competition;
    }
}
