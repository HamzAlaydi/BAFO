<?php

declare(strict_types=1);

namespace App\Support\Files;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may download a private file (ARCHITECTURE §4.6, §8.5). The module owning a purpose
 * registers its rule in its provider's boot():
 *
 *     app(FileAccessRegistry::class)->register('competition_attachment',
 *         fn (User $user, File $file): bool => ...);
 *
 * Public purposes (logos, avatars) need no rule: any signed-in user may fetch them (they are
 * also served from /storage). A private purpose without a rule is denied.
 */
final class FileAccessRegistry
{
    /**
     * @var array<string, Closure(Authenticatable, File): bool>
     */
    private array $rules = [];

    /**
     * @param  Closure(Authenticatable, File): bool  $rule
     */
    public function register(FilePurpose|string $purpose, Closure $rule): void
    {
        $this->rules[$purpose instanceof FilePurpose ? $purpose->value : $purpose] = $rule;
    }

    public function has(FilePurpose|string $purpose): bool
    {
        return isset($this->rules[$purpose instanceof FilePurpose ? $purpose->value : $purpose]);
    }

    /**
     * @param  Authenticatable  $user  App\Modules\Identity\Models\User
     */
    public function allows(Authenticatable $user, File $file): bool
    {
        if ($file->purpose->isPublic()) {
            return true;
        }

        $rule = $this->rules[$file->purpose->value] ?? null;

        return $rule !== null && $rule($user, $file) === true;
    }
}
