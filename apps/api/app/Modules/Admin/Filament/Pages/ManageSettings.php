<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Filament\Support\SettingsForm;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Admin\Support\AdminScope;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * §16 Settings: every §15.3 runtime setting (the keys modules registered), super admins only
 * (§8.7). Each changed key is saved through Platform's `UpdateAppSetting` and audited
 * (`setting.updated`). The page stays in release scope `core` (the release-scope select must
 * stay reachable), with the essential groups only (SettingsForm, RELEASE_SCOPE.md §11).
 *
 * @property-read Schema $form
 */
final class ManageSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $slug = 'settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminScope::visible(OpsSurface::Settings) && AdminActor::isSuperAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return Lang::get('settings.title');
    }

    public function getTitle(): string
    {
        return Lang::get('settings.title');
    }

    public function mount(): void
    {
        $this->form->fill(app(SettingsForm::class)->state());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(app(SettingsForm::class)->components())
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(Lang::get('settings.save'))->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        /** @var array<string, mixed> $state */
        $state = $this->form->getState();

        $changed = ModuleAction::run(static fn (): int => app(SettingsForm::class)->save($state, AdminActor::current()));

        Notification::make()
            ->success()
            ->title(Lang::get('settings.saved', ['count' => $changed]))
            ->send();

        $this->form->fill(app(SettingsForm::class)->state());
    }
}
