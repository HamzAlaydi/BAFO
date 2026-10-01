<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Subscriptions;

use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Billing\Actions\GrantSubscription;
use App\Modules\Billing\Models\Plan;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Organization;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * §16 "Grant subscription" → Billing `GrantSubscription` (a `source = grant` subscription with a
 * plan, seats, a period and a reason). On an organization page the organization is fixed; on the
 * subscriptions list the admin picks it.
 */
final class GrantSubscriptionAction
{
    public static function make(?Organization $organization = null): Action
    {
        return Action::make('grantSubscription')
            ->label(Lang::get('resources.subscriptions.actions.grant'))
            ->icon(Heroicon::OutlinedGift)
            ->modalHeading(Lang::get('resources.subscriptions.actions.grant'))
            ->schema([
                Select::make('organization_id')
                    ->label(Lang::get('fields.organization'))
                    ->hidden($organization !== null)
                    ->required($organization === null)
                    ->searchable()
                    ->getSearchResultsUsing(static fn (string $search): array => Organization::query()
                        ->where('status', OrganizationStatus::Active->value)
                        ->where(static fn ($query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('cr_number', 'like', "{$search}%"))
                        ->orderBy('name')
                        ->limit(20)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(static fn (mixed $value): ?string => Organization::query()->whereKey($value)->value('name')),
                Select::make('plan_id')
                    ->label(Lang::get('fields.plan'))
                    ->required()
                    ->options(static fn (): array => Plan::query()->where('is_active', true)->orderBy('sort_order')->get()
                        ->mapWithKeys(static fn (Plan $plan): array => [$plan->id => Display::translated($plan->name) ?? $plan->code])
                        ->all())
                    ->live()
                    ->afterStateUpdated(static function (Set $set, mixed $state): void {
                        $seats = Plan::query()->whereKey($state)->value('seats');
                        $set('seats', is_int($seats) ? $seats : 1);
                    }),
                TextInput::make('seats')
                    ->label(Lang::get('fields.seats'))
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1000)
                    ->default(1),
                DateTimePicker::make('starts_at')
                    ->label(Lang::get('fields.starts_at'))
                    ->required()
                    ->seconds(false)
                    ->default(static fn (): string => CarbonImmutable::now()->toDateTimeString()),
                DateTimePicker::make('ends_at')
                    ->label(Lang::get('fields.ends_at'))
                    ->required()
                    ->seconds(false)
                    ->after('starts_at')
                    ->default(static fn (): string => CarbonImmutable::now()->addYear()->toDateTimeString()),
                Textarea::make('reason')
                    ->label(Lang::get('fields.reason'))
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(static function (array $data) use ($organization): void {
                $target = $organization ?? Organization::query()->findOrFail($data['organization_id']);
                $plan = Plan::query()->findOrFail($data['plan_id']);

                ModuleAction::run(
                    static fn () => app(GrantSubscription::class)->handle(
                        $target,
                        $plan,
                        (int) $data['seats'],
                        CarbonImmutable::parse((string) $data['starts_at'], 'UTC'),
                        CarbonImmutable::parse((string) $data['ends_at'], 'UTC'),
                        (string) $data['reason'],
                        AdminActor::current(),
                    ),
                    Lang::get('resources.subscriptions.notifications.granted'),
                );
            });
    }
}
