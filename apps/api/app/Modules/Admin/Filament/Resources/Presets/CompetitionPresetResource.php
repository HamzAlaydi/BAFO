<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Presets;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\Presets\Pages\CreateCompetitionPreset;
use App\Modules\Admin\Filament\Resources\Presets\Pages\EditCompetitionPreset;
use App\Modules\Admin\Filament\Resources\Presets\Pages\ListCompetitionPresets;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Fields;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Enums\ResultPublication;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * §16 Lookups: competition presets (§5.2). `rules` holds the RulesInput keys of API.md §2.6
 * without prices (the issuer supplies them); Competitions validates a competition's rules when it
 * is saved or published. CRUD; the code is immutable after create.
 */
final class CompetitionPresetResource extends AdminResource
{
    protected static ?string $model = CompetitionPreset::class;

    protected static string $langKey = 'presets';

    protected static array $writableAbilities = ['create', 'update', 'delete'];

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Lookups;

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $slug = 'presets';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('code')->label(self::field('code'))
                    ->required()->maxLength(60)->regex('/^[a-z0-9_]+$/')->unique(ignoreRecord: true)->disabledOn('edit'),
                TextInput::make('sort_order')->label(self::field('sort_order'))->integer()->default(0)->required(),
                Fields::translated('name', self::field('name'), maxLength: 150)->columnSpanFull(),
                Fields::translated('description', self::field('description'), multiline: true, maxLength: 1000)->columnSpanFull(),
                Select::make('direction')->label(self::field('direction'))->required()->options(Display::options(Direction::class)),
                Select::make('format')->label(self::field('format'))->required()->live()->options(Display::options(Format::class)),
                Toggle::make('is_active')->label(self::field('is_active'))->default(true),
            ]),
            Section::make(self::field('rules'))->columns(3)->schema([
                Fields::money('rules.min_step_minor', self::field('min_step_minor')),
                TextInput::make('rules.min_step_bps')->label(self::field('min_step_bps'))->integer()->minValue(1)->maxValue(10000)
                    ->helperText(self::field('min_step_bps_help')),
                Select::make('rules.amount_granularity_minor')->label(self::field('granularity'))->required()
                    ->options(['1' => self::field('granularity_halala'), '100' => self::field('granularity_riyal')])
                    ->formatStateUsing(static fn (mixed $state): string => (string) ($state ?? 100))
                    ->dehydrateStateUsing(static fn (mixed $state): int => (int) $state),
                Select::make('rules.must_beat')->label(self::field('must_beat'))->options(Display::options(MustBeat::class))
                    ->helperText(self::field('must_beat_help')),
                Select::make('rules.rank_visibility')->label(self::field('rank_visibility'))->required()
                    ->options(Display::options(RankVisibility::class)),
                Select::make('rules.result_publication')->label(self::field('result_publication'))->required()
                    ->options(Display::options(ResultPublication::class)),
                Toggle::make('rules.show_prices')->label(self::field('show_prices')),
                TextInput::make('rules.final_window_minutes')->label(self::field('final_window_minutes'))->integer()->minValue(1),
                TextInput::make('rules.min_participants')->label(self::field('min_participants'))->integer()->minValue(1)->default(2)->required(),
                Fieldset::make(self::field('auto_extend'))->columns(4)->columnSpanFull()->schema([
                    Toggle::make('rules.auto_extend.enabled')->label(self::field('enabled'))->live(),
                    TextInput::make('rules.auto_extend.window_seconds')->label(self::field('window_seconds'))->integer()->minValue(1)
                        ->required(static fn (Get $get): bool => (bool) $get('rules.auto_extend.enabled')),
                    TextInput::make('rules.auto_extend.by_seconds')->label(self::field('by_seconds'))->integer()->minValue(1)
                        ->required(static fn (Get $get): bool => (bool) $get('rules.auto_extend.enabled')),
                    TextInput::make('rules.auto_extend.max_extensions')->label(self::field('max_extensions'))->integer()->minValue(1)
                        ->required(static fn (Get $get): bool => (bool) $get('rules.auto_extend.enabled')),
                ]),
                Fieldset::make(self::field('bafo_round'))->columns(2)->columnSpanFull()->schema([
                    Toggle::make('rules.bafo_round.enabled')->label(self::field('enabled'))->live(),
                    TextInput::make('rules.bafo_round.duration_minutes')->label(self::field('duration_minutes'))->integer()->minValue(1)
                        ->required(static fn (Get $get): bool => (bool) $get('rules.bafo_round.enabled')),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(self::field('code'))->searchable()->fontFamily('mono'),
                TextColumn::make('name')->label(self::field('name'))
                    ->state(static fn (CompetitionPreset $record): ?string => $record->translated('name')),
                TextColumn::make('direction')->label(self::field('direction'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('format')->label(self::field('format'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                IconColumn::make('is_active')->label(self::field('is_active'))->boolean(),
                TextColumn::make('sort_order')->label(self::field('sort_order'))->sortable(),
            ])
            ->filters([TernaryFilter::make('is_active')->label(self::field('is_active'))])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompetitionPresets::route('/'),
            'create' => CreateCompetitionPreset::route('/create'),
            'edit' => EditCompetitionPreset::route('/{record}/edit'),
        ];
    }
}
