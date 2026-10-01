<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Auth;

use App\Modules\Admin\Actions\UpdateAdmin;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Models\Admin;
use App\Support\Auth\Actor;
use Filament\Auth\Pages\EditProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use SensitiveParameter;

/**
 * The admin's own profile (name, e-mail, password) and the MFA management of §8.7. Saves go
 * through the audited `UpdateAdmin` Action; the role and the active flag are not editable here.
 */
final class EditAdminProfile extends EditProfile
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        if (! $record instanceof Admin) {
            return parent::handleRecordUpdate($record, $data);
        }

        /** @var array{name?: string, email?: string, password?: string|null} $changes */
        $changes = Arr::only($data, ['name', 'email', 'password']);

        return ModuleAction::run(static fn (): Admin => app(UpdateAdmin::class)->handle($record, $changes, Actor::forAdmin($record, request())));
    }
}
