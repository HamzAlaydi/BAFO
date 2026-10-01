<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\AuditLogs;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Modules\Admin\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLog;
use App\Support\Auth\ActorType;
use App\Support\Database\MorphMap;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * §16 Audit log: the append-only `audit_logs`, read-only, with filters (action, actor, organization,
 * subject, dates). Secrets are already redacted by AuditLogger.
 */
final class AuditLogResource extends AdminResource
{
    protected static ?string $model = AuditLog::class;

    protected static string $langKey = 'audit_logs';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $slug = 'audit-log';

    /**
     * @return Builder<AuditLog>
     */
    public static function getEloquentQuery(): Builder
    {
        return AuditLog::query()
            ->select('audit_logs.*')
            ->addSelect(['organization_name' => Organization::query()
                ->withoutGlobalScopes()
                ->select('name')
                ->whereColumn('organizations.id', 'audit_logs.organization_id')
                ->limit(1)]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')->label(self::field('occurred_at'))->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('action')->label(self::field('action'))->badge()->searchable(),
                TextColumn::make('actor_type')->label(self::field('actor_type'))
                    ->formatStateUsing(static fn (mixed $state): string => self::actorType($state)),
                TextColumn::make('actor_label')->label(self::field('actor'))->searchable()->placeholder('—'),
                TextColumn::make('organization_name')->label(self::field('organization'))->placeholder('—'),
                TextColumn::make('subject')->label(self::field('subject'))->placeholder('—')
                    ->state(static fn (AuditLog $record): ?string => $record->subject_type !== null
                        ? $record->subject_type.($record->subject_public_id !== null ? ' · '.$record->subject_public_id : '')
                        : null),
                TextColumn::make('subject_public_id')->label(self::field('subject_id'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('channel')->label(self::field('channel'))->placeholder('—')->toggleable(),
                TextColumn::make('ip')->label(self::field('ip'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_id')->label(self::field('request_id'))->searchable()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label(self::field('action'))->multiple()->searchable()
                    ->options(static fn (): array => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                SelectFilter::make('actor_type')->label(self::field('actor_type'))
                    ->options(array_combine(
                        array_map(static fn (ActorType $type): string => $type->value, ActorType::cases()),
                        array_map(static fn (ActorType $type): string => self::actorType($type), ActorType::cases()),
                    )),
                SelectFilter::make('subject_type')->label(self::field('subject_type'))
                    ->options(array_combine(array_keys(MorphMap::ALIASES), array_keys(MorphMap::ALIASES))),
                Filter::make('organization')
                    ->schema([
                        Select::make('organization_id')->label(self::field('organization'))->searchable()
                            ->getSearchResultsUsing(static fn (string $search): array => Organization::query()->withoutGlobalScopes()
                                ->where('name', 'ilike', "%{$search}%")->orderBy('name')->limit(20)->pluck('name', 'id')->all())
                            ->getOptionLabelUsing(static fn (mixed $value): ?string => Organization::query()->withoutGlobalScopes()->whereKey($value)->value('name')),
                    ])
                    ->query(static fn (Builder $query, array $data): Builder => filled($data['organization_id'] ?? null)
                        ? $query->where('audit_logs.organization_id', $data['organization_id'])
                        : $query),
                Filter::make('occurred_at')
                    ->schema([
                        DatePicker::make('from')->label(self::field('from')),
                        DatePicker::make('until')->label(self::field('until')),
                    ])
                    ->query(static fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, static fn (Builder $q, string $date): Builder => $q
                            ->where('occurred_at', '>=', CarbonImmutable::parse($date, Display::TIMEZONE)->startOfDay()->utc()))
                        ->when($data['until'] ?? null, static fn (Builder $q, string $date): Builder => $q
                            ->where('occurred_at', '<', CarbonImmutable::parse($date, Display::TIMEZONE)->addDay()->startOfDay()->utc()))),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('occurred_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('occurred_at')->label(self::field('occurred_at'))->dateTime(Display::PRECISE),
                TextEntry::make('action')->label(self::field('action'))->badge(),
                TextEntry::make('actor_type')->label(self::field('actor_type'))
                    ->formatStateUsing(static fn (mixed $state): string => self::actorType($state)),
                TextEntry::make('actor_label')->label(self::field('actor'))->placeholder('—'),
                TextEntry::make('organization_name')->label(self::field('organization'))->placeholder('—'),
                TextEntry::make('subject_type')->label(self::field('subject_type'))->placeholder('—'),
                TextEntry::make('subject_public_id')->label(self::field('subject_id'))->placeholder('—')->copyable(),
                TextEntry::make('channel')->label(self::field('channel'))->placeholder('—'),
                TextEntry::make('ip')->label(self::field('ip'))->placeholder('—'),
                TextEntry::make('request_id')->label(self::field('request_id'))->placeholder('—')->copyable(),
                TextEntry::make('user_agent')->label(self::field('user_agent'))->placeholder('—')->columnSpan(2),
            ]),
            Section::make(self::field('changes'))->schema([
                KeyValueEntry::make('changes')->hiddenLabel()->placeholder('—')
                    ->keyLabel(self::field('key'))->valueLabel(self::field('value'))
                    ->state(static fn (AuditLog $record): array => self::flatten($record->changes)),
            ]),
            Section::make(self::field('meta'))->schema([
                KeyValueEntry::make('meta')->hiddenLabel()->placeholder('—')
                    ->keyLabel(self::field('key'))->valueLabel(self::field('value'))
                    ->state(static fn (AuditLog $record): array => self::flatten($record->meta)),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }

    /**
     * `{field: {from, to}}` and nested meta as dotted keys with printable values.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, string>
     */
    public static function flatten(?array $values): array
    {
        $flat = [];

        foreach (Arr::dot($values ?? []) as $key => $value) {
            $flat[(string) $key] = match (true) {
                $value === null => 'null',
                is_bool($value) => $value ? 'true' : 'false',
                is_scalar($value) => (string) $value,
                default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            };
        }

        return $flat;
    }

    private static function actorType(mixed $state): string
    {
        $value = $state instanceof ActorType ? $state->value : (is_string($state) ? $state : '');

        return Lang::get('audit.actor_types.'.$value);
    }
}
