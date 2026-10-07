<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Subscriptions;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * §16 Subscriptions: list and filter; "Grant subscription" → Billing `GrantSubscription`.
 */
final class SubscriptionResource extends AdminResource
{
    protected static ?string $model = Subscription::class;

    protected static string $langKey = 'subscriptions';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Billing;

    protected static ?OpsSurface $opsSurface = OpsSurface::Subscriptions;

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    /**
     * Soft-deleted organizations stay visible (account deletion keeps their records).
     *
     * @return Builder<Subscription>
     */
    public static function getEloquentQuery(): Builder
    {
        return Subscription::query()->with(self::withOrganization());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('plan'))
            ->columns([
                TextColumn::make('organization.name')->label(self::field('organization'))->searchable()
                    ->url(static fn (Subscription $record): ?string => OrganizationResource::link($record->organization)),
                TextColumn::make('plan')->label(self::field('plan'))
                    ->state(static fn (Subscription $record): ?string => Display::translated($record->plan->name)),
                TextColumn::make('source')->label(self::field('source'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('interval')->label(self::field('interval'))->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('seats')->label(self::field('seats')),
                TextColumn::make('starts_at')->label(self::field('starts_at'))->dateTime(Display::DATE_TIME)->placeholder('—')->sortable(),
                TextColumn::make('ends_at')->label(self::field('ends_at'))->dateTime(Display::DATE_TIME)->placeholder('—')->sortable(),
                TextColumn::make('total_minor')->label(self::field('total'))->placeholder('—')
                    ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null),
                TextColumn::make('grant_reason')->label(self::field('grant_reason'))->placeholder('—')->limit(40)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME)->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(self::field('status'))->options(Display::options(SubscriptionStatus::class)),
                SelectFilter::make('source')->label(self::field('source'))->options(Display::options(SubscriptionSource::class)),
                SelectFilter::make('plan_id')->label(self::field('plan'))
                    ->options(static fn (): array => Plan::query()->orderBy('sort_order')->get()
                        ->mapWithKeys(static fn (Plan $plan): array => [$plan->id => Display::translated($plan->name) ?? $plan->code])
                        ->all()),
                Filter::make('current')->label(self::field('current'))
                    ->query(static fn (Builder $query): Builder => $query
                        ->where('status', SubscriptionStatus::Active->value)
                        ->where('starts_at', '<=', CarbonImmutable::now())
                        ->where('ends_at', '>', CarbonImmutable::now())),
            ])
            ->headerActions([GrantSubscriptionAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
        ];
    }
}
