<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\WebhookEndpoints;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Resources\WebhookEndpoints\Pages\ListWebhookEndpoints;
use App\Modules\Admin\Filament\Resources\WebhookEndpoints\Pages\ViewWebhookEndpoint;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Models\WebhookEndpoint;
use BackedEnum;
use Filament\Actions\ViewAction;
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
 * §16 Webhook endpoints: read-only per organization. The signing secret is never shown.
 */
final class WebhookEndpointResource extends AdminResource
{
    protected static ?string $model = WebhookEndpoint::class;

    protected static string $langKey = 'webhook_endpoints';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Integrations;

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $recordTitleAttribute = 'url';

    /**
     * Soft-deleted organizations stay visible (account deletion keeps their records).
     *
     * @return Builder<WebhookEndpoint>
     */
    public static function getEloquentQuery(): Builder
    {
        return WebhookEndpoint::query()->with(self::withOrganization());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->label(self::field('url'))->searchable()->limit(50),
                TextColumn::make('organization.name')->label(self::field('organization'))->searchable(),
                TextColumn::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('disabled_reason')->label(self::field('disabled_reason'))->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('event_types')->label(self::field('event_types'))->badge()->limitList(3)->toggleable(),
                TextColumn::make('last_success_at')->label(self::field('last_success_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextColumn::make('last_failure_at')->label(self::field('last_failure_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(self::field('status'))->options(Display::options(WebhookEndpointStatus::class)),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(self::field('section_endpoint'))->columns(3)->schema([
                TextEntry::make('url')->label(self::field('url'))->columnSpan(2)->copyable(),
                TextEntry::make('organization.name')->label(self::field('organization'))
                    ->url(static fn (WebhookEndpoint $record): ?string => OrganizationResource::link($record->organization)),
                TextEntry::make('description')->label(self::field('description'))->placeholder('—'),
                TextEntry::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextEntry::make('disabled_reason')->label(self::field('disabled_reason'))->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextEntry::make('event_types')->label(self::field('event_types'))->badge()->columnSpanFull(),
                TextEntry::make('failing_since')->label(self::field('failing_since'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('last_success_at')->label(self::field('last_success_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('last_failure_at')->label(self::field('last_failure_at'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                TextEntry::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookEndpoints::route('/'),
            'view' => ViewWebhookEndpoint::route('/{record}'),
        ];
    }
}
