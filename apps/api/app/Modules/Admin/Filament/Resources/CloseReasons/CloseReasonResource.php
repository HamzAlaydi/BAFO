<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\CloseReasons;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\CloseReasons\Pages\CreateCloseReason;
use App\Modules\Admin\Filament\Resources\CloseReasons\Pages\EditCloseReason;
use App\Modules\Admin\Filament\Resources\CloseReasons\Pages\ListCloseReasons;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Fields;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * §16 Lookups: close reasons (§5.2): cancel, not awarded, award justification and void offer.
 * CRUD; the code and the kind are immutable after create.
 */
final class CloseReasonResource extends AdminResource
{
    protected static ?string $model = CloseReason::class;

    protected static string $langKey = 'close_reasons';

    protected static array $writableAbilities = ['create', 'update', 'delete'];

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Lookups;

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('code')->label(self::field('code'))
                    ->required()->maxLength(60)->regex('/^[a-z0-9_]+$/')->unique(ignoreRecord: true)->disabledOn('edit'),
                Select::make('kind')->label(self::field('kind'))->required()
                    ->options(Display::options(CloseReasonKind::class))->disabledOn('edit'),
                Fields::translated('name', self::field('name'), maxLength: 200)->columnSpanFull(),
                Toggle::make('requires_note')->label(self::field('requires_note')),
                TextInput::make('sort_order')->label(self::field('sort_order'))->integer()->default(0)->required(),
                Toggle::make('is_active')->label(self::field('is_active'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(self::field('code'))->searchable()->fontFamily('mono'),
                TextColumn::make('kind')->label(self::field('kind'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('name')->label(self::field('name'))
                    ->state(static fn (CloseReason $record): ?string => $record->translated('name')),
                IconColumn::make('requires_note')->label(self::field('requires_note'))->boolean(),
                IconColumn::make('is_active')->label(self::field('is_active'))->boolean(),
                TextColumn::make('sort_order')->label(self::field('sort_order'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')->label(self::field('kind'))->options(Display::options(CloseReasonKind::class)),
                TernaryFilter::make('is_active')->label(self::field('is_active')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCloseReasons::route('/'),
            'create' => CreateCloseReason::route('/create'),
            'edit' => EditCloseReason::route('/{record}/edit'),
        ];
    }
}
