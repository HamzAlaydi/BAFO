<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Vouchers;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Resources\Vouchers\Pages\ListVouchers;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Billing\Enums\CouponKind;
use App\Modules\Billing\Models\Coupon;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * §16 Vouchers: read-only, with their balance. Vouchers are organization-scoped coupons issued
 * for unused sponsored passes (Billing `IssueSponsorshipVoucher`).
 */
final class VoucherResource extends AdminResource
{
    protected static ?string $model = Coupon::class;

    protected static string $langKey = 'vouchers';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Billing;

    protected static ?int $navigationSort = 25;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $slug = 'vouchers';

    /**
     * @return Builder<Coupon>
     */
    public static function getEloquentQuery(): Builder
    {
        return Coupon::query()->where('kind', CouponKind::Voucher->value)->with(self::withOrganization());
    }

    public static function table(Table $table): Table
    {
        $money = static fn (mixed $state): ?string => is_int($state) ? Display::money($state) : null;

        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('sourceCompetition'))
            ->columns([
                TextColumn::make('code')->label(self::field('code'))->searchable()->fontFamily('mono')->copyable(),
                TextColumn::make('organization.name')->label(self::field('organization'))->searchable()->placeholder('—')
                    ->url(static fn (Coupon $record): ?string => OrganizationResource::link($record->organization)),
                TextColumn::make('amount_minor')->label(self::field('value'))->formatStateUsing($money),
                TextColumn::make('balance_minor')->label(self::field('balance'))->formatStateUsing($money)->weight('bold'),
                TextColumn::make('sourceCompetition.reference_no')->label(self::field('source_competition'))->placeholder('—'),
                TextColumn::make('valid_until')->label(self::field('valid_until'))->dateTime(Display::DATE_TIME)->placeholder('—'),
                IconColumn::make('is_active')->label(self::field('is_active'))->boolean(),
                TextColumn::make('reason')->label(self::field('reason'))->placeholder('—')->limit(40)->toggleable(),
                TextColumn::make('created_at')->label(self::field('created_at'))->dateTime(Display::DATE_TIME)->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(self::field('is_active')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVouchers::route('/'),
        ];
    }
}
