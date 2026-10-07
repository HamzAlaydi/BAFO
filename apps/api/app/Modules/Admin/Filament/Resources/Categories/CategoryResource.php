<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Categories;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\Categories\Pages\CreateCategory;
use App\Modules\Admin\Filament\Resources\Categories\Pages\EditCategory;
use App\Modules\Admin\Filament\Resources\Categories\Pages\ListCategories;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Fields;
use App\Modules\Catalog\Models\Category;
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
 * §16 Lookups: categories (§5.2), including `auction_allowed` (the R5 guardrail) and the "Other"
 * flag. CRUD; the code is immutable after create.
 */
final class CategoryResource extends AdminResource
{
    protected static ?string $model = Category::class;

    protected static string $langKey = 'categories';

    protected static array $writableAbilities = ['create', 'update', 'delete'];

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Lookups;

    protected static ?OpsSurface $opsSurface = OpsSurface::Categories;

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('code')->label(self::field('code'))
                    ->required()->maxLength(40)->regex('/^[a-z0-9_]+$/')->unique(ignoreRecord: true)->disabledOn('edit'),
                TextInput::make('sort_order')->label(self::field('sort_order'))->integer()->default(0)->required(),
                Fields::translated('name', self::field('name'), maxLength: 150)->columnSpanFull(),
                Toggle::make('auction_allowed')->label(self::field('auction_allowed'))->default(true)
                    ->helperText(self::field('auction_allowed_help')),
                Toggle::make('is_other')->label(self::field('is_other'))
                    ->helperText(self::field('is_other_help')),
                Toggle::make('is_active')->label(self::field('is_active'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(self::field('code'))->searchable()->fontFamily('mono'),
                TextColumn::make('name_ar')->label(self::field('name_ar'))->state(static fn (Category $record): ?string => $record->translated('name', 'ar')),
                TextColumn::make('name_en')->label(self::field('name_en'))->state(static fn (Category $record): ?string => $record->translated('name', 'en')),
                IconColumn::make('auction_allowed')->label(self::field('auction_allowed'))->boolean(),
                IconColumn::make('is_other')->label(self::field('is_other'))->boolean(),
                IconColumn::make('is_active')->label(self::field('is_active'))->boolean(),
                TextColumn::make('sort_order')->label(self::field('sort_order'))->sortable(),
                TextColumn::make('updated_at')->label(self::field('updated_at'))->dateTime(Display::DATE_TIME)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(self::field('is_active')),
                TernaryFilter::make('auction_allowed')->label(self::field('auction_allowed')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
