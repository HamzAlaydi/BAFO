<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Regions;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\Regions\Pages\CreateRegion;
use App\Modules\Admin\Filament\Resources\Regions\Pages\EditRegion;
use App\Modules\Admin\Filament\Resources\Regions\Pages\ListRegions;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Fields;
use App\Modules\Catalog\Models\Region;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * §16 Lookups: regions (§5.2). CRUD; the code is immutable after create.
 */
final class RegionResource extends AdminResource
{
    protected static ?string $model = Region::class;

    protected static string $langKey = 'regions';

    protected static array $writableAbilities = ['create', 'update', 'delete'];

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Lookups;

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('code')->label(self::field('code'))
                    ->required()->maxLength(8)->regex('/^[A-Z0-9_]+$/')->unique(ignoreRecord: true)->disabledOn('edit'),
                TextInput::make('sort_order')->label(self::field('sort_order'))->integer()->default(0)->required(),
                Fields::translated('name', self::field('name'), maxLength: 150)->columnSpanFull(),
                Toggle::make('is_active')->label(self::field('is_active'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(self::field('code'))->searchable()->fontFamily('mono'),
                TextColumn::make('name_ar')->label(self::field('name_ar'))->state(static fn (Region $record): ?string => $record->translated('name', 'ar')),
                TextColumn::make('name_en')->label(self::field('name_en'))->state(static fn (Region $record): ?string => $record->translated('name', 'en')),
                IconColumn::make('is_active')->label(self::field('is_active'))->boolean(),
                TextColumn::make('sort_order')->label(self::field('sort_order'))->sortable(),
                TextColumn::make('updated_at')->label(self::field('updated_at'))->dateTime(Display::DATE_TIME)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TernaryFilter::make('is_active')->label(self::field('is_active'))])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegions::route('/'),
            'create' => CreateRegion::route('/create'),
            'edit' => EditRegion::route('/{record}/edit'),
        ];
    }
}
