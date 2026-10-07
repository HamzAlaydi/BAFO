<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ApiClients;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\ApiClients\Pages\ListApiClients;
use App\Modules\Admin\Filament\Resources\ApiClients\Pages\ViewApiClient;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Integrations\Actions\ApiClients\RevokeApiClient;
use App\Modules\Integrations\Actions\ApiClients\SetApiClientSuspension;
use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * §16 API clients: read-only per organization (the organization manages them in the dashboard).
 * Suspend / Reactivate → Integrations `SetApiClientSuspension`; Revoke → `RevokeApiClient`.
 * Keys are shown by prefix and last four characters only.
 */
final class ApiClientResource extends AdminResource
{
    protected static ?string $model = ApiClient::class;

    protected static string $langKey = 'api_clients';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Integrations;

    protected static ?OpsSurface $opsSurface = OpsSurface::ApiClients;

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Soft-deleted organizations stay visible (account deletion keeps their records).
     *
     * @return Builder<ApiClient>
     */
    public static function getEloquentQuery(): Builder
    {
        return ApiClient::query()->with(self::withOrganization());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->withCount('keys'))
            ->columns([
                TextColumn::make('name')->label(self::field('name'))->searchable(),
                TextColumn::make('organization.name')->label(self::field('organization'))->searchable(),
                TextColumn::make('public_id')->label(self::field('client_id'))->fontFamily('mono')->limit(12)->copyable(),
                TextColumn::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('scopes')->label(self::field('scopes'))->badge()->limitList(3)->toggleable(),
                TextColumn::make('keys_count')->label(self::field('keys_count')),
                TextColumn::make('last_used_at')->label(self::field('last_used_at'))->dateTime(Display::DATE_TIME)->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME)->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(self::field('status'))->options(Display::options(ApiClientStatus::class)),
            ])
            ->recordActions([ViewAction::make(), ...self::clientActions()])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return list<Action>
     */
    public static function clientActions(): array
    {
        return [
            Action::make('suspend')
                ->label(Lang::get('resources.api_clients.actions.suspend'))
                ->icon(Heroicon::OutlinedPauseCircle)
                ->color('warning')
                ->visible(static fn (ApiClient $record): bool => $record->status === ApiClientStatus::Active)
                ->requiresConfirmation()
                ->action(static fn (ApiClient $record) => ModuleAction::run(
                    static fn () => app(SetApiClientSuspension::class)->handle($record, true, AdminActor::current()),
                    Lang::get('resources.api_clients.notifications.suspended'),
                )),
            Action::make('reactivate')
                ->label(Lang::get('resources.api_clients.actions.reactivate'))
                ->icon(Heroicon::OutlinedPlayCircle)
                ->color('success')
                ->visible(static fn (ApiClient $record): bool => $record->status === ApiClientStatus::Suspended)
                ->requiresConfirmation()
                ->action(static fn (ApiClient $record) => ModuleAction::run(
                    static fn () => app(SetApiClientSuspension::class)->handle($record, false, AdminActor::current()),
                    Lang::get('resources.api_clients.notifications.reactivated'),
                )),
            Action::make('revoke')
                ->label(Lang::get('resources.api_clients.actions.revoke'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(static fn (ApiClient $record): bool => $record->status !== ApiClientStatus::Revoked)
                ->requiresConfirmation()
                ->modalDescription(Lang::get('resources.api_clients.actions.revoke_help'))
                ->action(static fn (ApiClient $record) => ModuleAction::run(
                    static fn () => app(RevokeApiClient::class)->handle($record, AdminActor::current()),
                    Lang::get('resources.api_clients.notifications.revoked'),
                )),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(self::field('section_client'))->columns(3)->schema([
                TextEntry::make('name')->label(self::field('name')),
                TextEntry::make('organization.name')->label(self::field('organization'))
                    ->url(static fn (ApiClient $record): ?string => OrganizationResource::link($record->organization)),
                TextEntry::make('public_id')->label(self::field('client_id'))->fontFamily('mono')->copyable(),
                TextEntry::make('description')->label(self::field('description'))->placeholder('—'),
                TextEntry::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextEntry::make('scopes')->label(self::field('scopes'))->badge(),
                TextEntry::make('last_used_at')->label(self::field('last_used_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('last_used_ip')->label(self::field('last_used_ip'))->placeholder('—'),
                TextEntry::make('revoked_at')->label(self::field('revoked_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
            ]),
            Section::make(self::field('section_keys'))->schema([
                RepeatableEntry::make('keys')->hiddenLabel()->placeholder('—')->columns(5)->schema([
                    TextEntry::make('masked')->label(self::field('key'))->fontFamily('mono')
                        ->state(static fn (ApiKey $record): string => $record->prefix.'…'.$record->last_four),
                    TextEntry::make('expires_at')->label(self::field('expires_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                    TextEntry::make('revoked_at')->label(self::field('revoked_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                    TextEntry::make('last_used_at')->label(self::field('last_used_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                    TextEntry::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME),
                ]),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiClients::route('/'),
            'view' => ViewApiClient::route('/{record}'),
        ];
    }
}
