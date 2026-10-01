<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Users;

use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Identity\Actions\ChangeMembershipStatus;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Membership;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * §16 Users: deactivate / reactivate the membership → Identity `ChangeMembershipStatus`
 * (`active ↔ inactive`; reactivation needs a free seat, 409 `seat_limit_reached`).
 */
final class MembershipStatusActions
{
    /**
     * @param  Closure(Model): ?Membership  $membership  the row's membership
     * @return list<Action>
     */
    public static function make(Closure $membership): array
    {
        return [
            Action::make('deactivateMembership')
                ->label(Lang::get('resources.users.actions.deactivate'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(static fn (Model $record): bool => $membership($record)?->status === MembershipStatus::Active)
                ->requiresConfirmation()
                ->action(static fn (Model $record) => self::change($membership($record), MembershipStatus::Inactive)),
            Action::make('reactivateMembership')
                ->label(Lang::get('resources.users.actions.reactivate'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('success')
                ->visible(static fn (Model $record): bool => $membership($record)?->status === MembershipStatus::Inactive)
                ->requiresConfirmation()
                ->action(static fn (Model $record) => self::change($membership($record), MembershipStatus::Active)),
        ];
    }

    private static function change(?Membership $membership, MembershipStatus $to): void
    {
        if ($membership === null) {
            return;
        }

        ModuleAction::run(
            static fn () => app(ChangeMembershipStatus::class)->handle($membership, $to, AdminActor::current()),
            Lang::get($to === MembershipStatus::Active ? 'resources.users.notifications.reactivated' : 'resources.users.notifications.deactivated'),
        );
    }
}
