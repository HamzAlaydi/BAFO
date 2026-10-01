<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Coupons;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Modules\Admin\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Modules\Admin\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Fields;
use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Enums\CouponScope;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Identity\Models\Organization;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * §16 Coupons: CRUD of coupons (kind `coupon`), super admins only (§8.7). Vouchers are the
 * read-only VoucherResource. The code is stored upper case and is immutable after create.
 */
final class CouponResource extends AdminResource
{
    protected static ?string $model = Coupon::class;

    protected static string $langKey = 'coupons';

    protected static bool $superAdminOnly = true;

    protected static array $writableAbilities = ['create', 'update', 'delete'];

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Billing;

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'code';

    /**
     * @return Builder<Coupon>
     */
    public static function getEloquentQuery(): Builder
    {
        return Coupon::query()->where('kind', CouponKind::Coupon->value)->with(self::withOrganization());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(self::field('section_coupon'))->columns(2)->schema([
                TextInput::make('code')->label(self::field('code'))
                    ->required()->maxLength(40)->regex('/^[A-Za-z0-9_-]+$/')
                    // Codes are stored upper case: compare the way they will be stored.
                    ->rules([static fn (?Coupon $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $taken = is_string($value) && Coupon::query()
                            ->where('code', mb_strtoupper(trim($value)))
                            ->when($record !== null, static fn ($query) => $query->whereKeyNot($record?->getKey()))
                            ->exists();

                        if ($taken) {
                            $fail('validation.unique')->translate();
                        }
                    }])
                    ->disabledOn('edit'),
                Select::make('applies_to')->label(self::field('applies_to'))->required()
                    ->options(Display::options(CouponScope::class))->default(CouponScope::Any->value),
                Select::make('discount_type')->label(self::field('discount_type'))->required()->live()
                    ->options(Display::options(DiscountType::class))->default(DiscountType::Percent->value),
                TextInput::make('percent_bps')->label(self::field('percent'))
                    ->suffix('%')
                    ->visible(static fn (Get $get): bool => self::isPercent($get('discount_type')))
                    ->required(static fn (Get $get): bool => self::isPercent($get('discount_type')))
                    ->regex('/^(100(\.0{1,2})?|\d{1,2}(\.\d{1,2})?)$/')
                    ->formatStateUsing(static fn (mixed $state): ?string => is_int($state) ? Fields::toDecimal($state) : null)
                    ->dehydrateStateUsing(static fn (mixed $state): ?int => Fields::toMinor($state)),
                Fields::money('amount_minor', self::field('amount'))
                    ->visible(static fn (Get $get): bool => ! self::isPercent($get('discount_type')))
                    ->required(static fn (Get $get): bool => ! self::isPercent($get('discount_type'))),
                Select::make('organization_id')->label(self::field('organization'))
                    ->helperText(self::field('organization_help'))
                    ->searchable()
                    ->getSearchResultsUsing(static fn (string $search): array => Organization::query()
                        ->where('name', 'ilike', "%{$search}%")->orderBy('name')->limit(20)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(static fn (mixed $value): ?string => Organization::query()->whereKey($value)->value('name')),
                TextInput::make('max_redemptions')->label(self::field('max_redemptions'))->integer()->minValue(1)
                    ->helperText(self::field('unlimited_help')),
                TextInput::make('per_organization_limit')->label(self::field('per_organization_limit'))->integer()->minValue(1)->default(1)
                    ->helperText(self::field('unlimited_help')),
                DateTimePicker::make('valid_from')->label(self::field('valid_from'))->seconds(false),
                DateTimePicker::make('valid_until')->label(self::field('valid_until'))->seconds(false)->after('valid_from'),
                Toggle::make('is_active')->label(self::field('is_active'))->default(true),
                Textarea::make('reason')->label(self::field('reason'))->maxLength(1000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(self::field('code'))->searchable()->fontFamily('mono')->copyable(),
                TextColumn::make('discount')->label(self::field('discount'))
                    ->state(static fn (Coupon $record): ?string => self::discount($record)),
                TextColumn::make('applies_to')->label(self::field('applies_to'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('organization.name')->label(self::field('organization'))->placeholder(self::field('everyone')),
                TextColumn::make('redemptions')->label(self::field('redemptions'))
                    ->state(static fn (Coupon $record): string => $record->redemptions_count.' / '.($record->max_redemptions ?? '∞')),
                TextColumn::make('valid_until')->label(self::field('valid_until'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                IconColumn::make('is_active')->label(self::field('is_active'))->boolean(),
                TextColumn::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME)->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(self::field('is_active')),
                SelectFilter::make('applies_to')->label(self::field('applies_to'))->options(Display::options(CouponScope::class)),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Normalises the form data for the model: the kind, and only the value of the chosen type.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $percent = self::isPercent($data['discount_type'] ?? null);

        $data['percent_bps'] = $percent ? ($data['percent_bps'] ?? null) : null;
        $data['amount_minor'] = $percent ? null : ($data['amount_minor'] ?? null);

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }

    private static function isPercent(mixed $type): bool
    {
        return $type === DiscountType::Percent || $type === DiscountType::Percent->value;
    }

    private static function discount(Coupon $coupon): ?string
    {
        if ($coupon->discount_type === DiscountType::Percent) {
            return $coupon->percent_bps !== null ? rtrim(rtrim(Fields::toDecimal($coupon->percent_bps), '0'), '.').'%' : null;
        }

        return Display::money($coupon->amount_minor);
    }
}
