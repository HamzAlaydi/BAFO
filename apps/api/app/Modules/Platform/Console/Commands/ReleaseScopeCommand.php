<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console\Commands;

use App\Modules\Platform\Actions\UpdateAppSetting;
use App\Support\Auth\Actor;
use App\Support\Features\FeatureFlags;
use App\Support\Features\ReleaseScope;
use Illuminate\Console\Command;

/**
 * `platform:release-scope {core|full}` (RELEASE_SCOPE.md §1.1): sets the admin setting
 * `platform.release_scope` through UpdateAppSetting (audited as `setting.updated` by the system
 * actor) for scripts, e2e set-up and support. Without an argument it prints the current scope
 * and the flags derived from it.
 */
final class ReleaseScopeCommand extends Command
{
    protected $signature = 'platform:release-scope {scope? : core or full; omit to print the current scope and its flags}';

    protected $description = 'Show or set the release scope (platform.release_scope) that hides advanced features from the apps';

    public function handle(FeatureFlags $flags, UpdateAppSetting $update): int
    {
        $argument = $this->argument('scope');

        if (is_string($argument)) {
            $scope = ReleaseScope::tryFrom($argument);

            if ($scope === null) {
                $this->components->error("Unknown release scope [{$argument}]. Use one of: ".implode(', ', ReleaseScope::values()).'.');

                return self::INVALID;
            }

            $update->handle(FeatureFlags::SETTING_KEY, $scope->value, Actor::system());

            $this->components->info("Release scope set to [{$scope->value}].");
        }

        $this->components->twoColumnDetail('<fg=green;options=bold>release_scope</>', $flags->scope()->value);

        foreach ($flags->all() as $feature => $enabled) {
            $this->components->twoColumnDetail($feature, $enabled ? '<fg=green>on</>' : '<fg=gray>off</>');
        }

        return self::SUCCESS;
    }
}
