<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Plans;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\Plans\Pages\CreatePlan;
use App\Modules\Admin\Filament\Resources\Plans\Pages\EditPlan;
use App\Modules\Admin\Filament\Resources\Plans\Pages\ListPlans;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Fields;
use App\Modules\Billing\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * §16 Plans: CRUD, super admins only (§8.7: operators cannot manage plans or prices). Prices are
 * typed in SAR and stored in halalas, excl. VAT. The code is immutable after create.
 */
final class PlanResource extends AdminResource
{
    protected static ?string $model = Plan::class;

    protected static string $langKey = 'plans';

    protected static bool $superAdminOnly = true;

    protected static array $writableAbilities = ['create', 'update', 'delete'];

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Billing;

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(self::field('section_plan'))->columns(2)->schema([
                TextInput::make('code')->label(self::field('code'))
                    ->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                TextInput::make('sort_order')->label(self::field('sort_order'))->integer()->default(0)->required(),
                Fields::translated('name', self::field('name'))->columnSpanFull(),
                Fields::translated('description', self::field('description'), required: false, multiline: true, maxLength: 1000)->columnSpanFull(),
                TextInput::make('seats')->label(self::field('seats'))->integer()->minValue(1)->maxValue(1000)
                    ->helperText(self::field('seats_help')),
                Toggle::make('is_custom')->label(self::field('is_custom')),
                Toggle::make('is_featured')->label(self::field('is_featured')),
                Toggle::make('is_active')->label(self::field('is_active'))->default(true),
            ]),
            Section::make(self::field('section_prices'))->description(self::field('prices_help'))->columns(2)->schema([
                Fields::money('monthly_price_minor', self::field('monthly_price')),
                Fields::money('annual_price_minor', self::field('annual_price')),
                Fields::money('monthly_list_price_minor', self::field('monthly_list_price')),
                Fields::money('annual_list_price_minor', self::field('annual_list_price')),
            ]),
            Section::make(self::field('features'))->schema([
                Repeater::make('features')->hiddenLabel()->defaultItems(0)->reorderable()->columns(2)->schema([
                    TextInput::make('ar')->label(self::field('feature_ar'))->required()->maxLength(200),
                    TextInput::make('en')->label(self::field('feature_en'))->required()->maxLength(200),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $money = static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null;

        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->withCount('subscriptions'))
            ->columns([
                TextColumn::make('code')->label(self::field('code'))->searchable()->fontFamily('mono'),
                TextColumn::make('name')->label(self::field('name'))
                    ->state(static fn (Plan $record): ?string => Display::translated($record->name)),
                TextColumn::make('seats')->label(self::field('seats'))->placeholder('—'),
                TextColumn::make('monthly_price_minor')->label(self::field('monthly_price'))->placeholder('—')->formatStateUsing($money),
                TextColumn::make('annual_price_minor')->label(self::field('annual_price'))->placeholder('—')->formatStateUsing($money),
                IconColumn::make('is_featured')->label(self::field('is_featured'))->boolean(),
                IconColumn::make('is_active')->label(self::field('is_active'))->boolean(),
                TextColumn::make('subscriptions_count')->label(self::field('subscriptions_count')),
                TextColumn::make('sort_order')->label(self::field('sort_order'))->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    /**
     * An empty description is stored as null, not as `{"ar": null, "en": null}`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $description = $data['description'] ?? null;

        if (is_array($description) && blank($description['ar'] ?? null) && blank($description['en'] ?? null)) {
            $data['description'] = null;
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
