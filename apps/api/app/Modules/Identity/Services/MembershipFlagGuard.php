<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * `team.manage` is not a way around `can_award` and `can_purchase` (SECURITY_REVIEW S-02): a team
 * editor may only grant a flag whose permission it holds itself (`competitions.award`,
 * `billing.purchase`; the owner holds both). Revoking a flag, or resending one that does not
 * change, is always allowed.
 */
final class MembershipFlagGuard
{
    /**
     * The permission behind each membership flag (ARCHITECTURE §8.1).
     *
     * @var array<string, Permission>
     */
    private const array FLAGS = [
        'can_award' => Permission::CompetitionsAward,
        'can_purchase' => Permission::BillingPurchase,
    ];

    public function holds(User $editor, string $flag): bool
    {
        return $editor->hasPermission(self::FLAGS[$flag]);
    }

    /**
     * @param  array<string, mixed>  $requested  flag => requested value (true grants)
     * @param  array<string, bool>  $current  flag => current value (false for a new member)
     *
     * @throws ValidationException 422 on each flag the editor may not grant
     */
    public function assertGrantable(User $editor, array $requested, array $current = []): void
    {
        $messages = [];

        foreach (array_keys(self::FLAGS) as $flag) {
            $grants = ($requested[$flag] ?? null) === true && ($current[$flag] ?? false) !== true;

            if ($grants && ! $this->holds($editor, $flag)) {
                $message = __('identity.validation.flag_not_held');
                $messages[$flag] = [is_string($message) ? $message : 'flag_not_held'];
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }
}
