<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Admins;

use App\Modules\Admin\Actions\DeleteAdmin;
use App\Modules\Admin\Actions\ResetAdminMfa;
use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\Admins\Pages\CreateAdminAccount;
use App\Modules\Admin\Filament\Resources\Admins\Pages\EditAdminAccount;
use App\Modules\Admin\Filament\Resources\Admins\Pages\ListAdminAccounts;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Support\AdminActor;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/**
 * §16 Admins: CRUD and "Reset MFA", super admins only (AdminPolicy). Writes go through the Admin
 * Actions (CreateAdmin, UpdateAdmin, DeleteAdmin, ResetAdminMfa), each audited.
 */
final class AdminAccountResource extends AdminResource
{
    protected static ?string $model = Admin::class;

    protected static string $langKey = 'admins';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    protected static ?OpsSurface $opsSurface = OpsSurface::Admins;

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'admins';

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $ability = $action instanceof BackedEnum ? (string) $action->value : ($action instanceof UnitEnum ? $action->name : $action);

        return Gate::forUser(Auth::guard('admin')->user())->inspect($ability, $record ?? Admin::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label(self::field('name'))->required()->maxLength(150),
                TextInput::make('email')->label(self::field('email'))->required()->email()->maxLength(255)
                    // E-mails are stored lower case: compare the way they will be stored.
                    ->rules([static fn (?Admin $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $taken = is_string($value) && Admin::query()
                            ->where('email', mb_strtolower(trim($value)))
                            ->when($record !== null, static fn ($query) => $query->whereKeyNot($record?->getKey()))
                            ->exists();

                        if ($taken) {
                            $fail('validation.unique')->translate();
                        }
                    }]),
                Select::make('role')->label(self::field('role'))->required()
                    ->options(Display::options(AdminRole::class))->default(AdminRole::Operator->value),
                Toggle::make('is_active')->label(self::field('is_active'))->default(true),
                TextInput::make('password')->label(self::field('password'))->password()->revealable()
                    ->rule(Password::min(12))
                    ->required(static fn (string $operation): bool => $operation === 'create')
                    ->helperText(static fn (string $operation): ?string => $operation === 'edit' ? self::field('password_help') : null)
                    ->confirmed(),
                TextInput::make('password_confirmation')->label(self::field('password_confirmation'))->password()->revealable()
                    ->requiredWith('password')
                    ->dehydrated(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(self::field('name'))->searchable(),
                TextColumn::make('email')->label(self::field('email'))->searchable(),
                TextColumn::make('role')->label(self::field('role'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                IconColumn::make('is_active')->label(self::field('is_active'))->boolean(),
                IconColumn::make('mfa')->label(self::field('mfa'))->boolean()
                    ->state(static fn (Admin $record): bool => $record->app_authentication_secret !== null),
                TextColumn::make('last_login_at')->label(self::field('last_login_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextColumn::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME)->toggleable(),
            ])
            ->recordActions([EditAction::make(), self::resetMfaAction(), self::deleteAction()])
            ->defaultSort('name');
    }

    public static function resetMfaAction(): Action
    {
        return Action::make('resetMfa')
            ->label(Lang::get('resources.admins.actions.reset_mfa'))
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->visible(static fn (Admin $record): bool => Gate::forUser(Auth::guard('admin')->user())->allows('resetMfa', $record))
            ->requiresConfirmation()
            ->modalDescription(Lang::get('resources.admins.actions.reset_mfa_help'))
            ->action(static fn (Admin $record) => ModuleAction::run(
                static fn () => app(ResetAdminMfa::class)->handle($record, AdminActor::current()),
                Lang::get('resources.admins.notifications.mfa_reset'),
            ));
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(static function (Admin $record): bool {
                ModuleAction::run(static fn () => app(DeleteAdmin::class)->handle($record, AdminActor::current()));

                return true;
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminAccounts::route('/'),
            'create' => CreateAdminAccount::route('/create'),
            'edit' => EditAdminAccount::route('/{record}/edit'),
        ];
    }
}
