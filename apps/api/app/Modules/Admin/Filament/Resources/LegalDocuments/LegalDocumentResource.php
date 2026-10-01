<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\LegalDocuments;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Filament\Resources\LegalDocuments\Pages\CreateLegalDocument;
use App\Modules\Admin\Filament\Resources\LegalDocuments\Pages\EditLegalDocument;
use App\Modules\Admin\Filament\Resources\LegalDocuments\Pages\ListLegalDocuments;
use App\Modules\Admin\Filament\Resources\LegalDocuments\Pages\ViewLegalDocument;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Platform\Actions\PublishLegalDocument;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * §16 Legal documents: create and edit drafts (Platform `SaveLegalDocumentDraft`), publish a
 * version (`PublishLegalDocument`). A published version is immutable (consents point at it):
 * publish a new version instead.
 */
final class LegalDocumentResource extends AdminResource
{
    protected static ?string $model = LegalDocument::class;

    protected static string $langKey = 'legal_documents';

    protected static array $writableAbilities = ['create', 'update'];

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * Published versions are immutable (consents point at them).
     */
    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if ($record instanceof LegalDocument && $record->published_at !== null) {
            return Response::deny();
        }

        return parent::getEditAuthorizationResponse($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                Select::make('code')->label(self::field('code'))->required()
                    ->options(Display::options(LegalDocumentCode::class)),
                Select::make('locale')->label(self::field('locale'))->required()
                    ->options(['ar' => Lang::get('common.locale_ar'), 'en' => Lang::get('common.locale_en')]),
                TextInput::make('version')->label(self::field('version'))->required()->maxLength(20)
                    ->placeholder('2026-10-01')
                    ->unique(ignoreRecord: true, modifyRuleUsing: static fn (Unique $rule, Get $get): Unique => $rule
                        ->where('code', (string) $get('code'))
                        ->where('locale', (string) $get('locale'))),
                TextInput::make('title')->label(self::field('title'))->required()->maxLength(200)->columnSpanFull(),
                MarkdownEditor::make('body_markdown')->label(self::field('body'))->required()->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('code')->label(self::field('code'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextEntry::make('locale')->label(self::field('locale')),
                TextEntry::make('version')->label(self::field('version')),
                TextEntry::make('published_at')->label(self::field('published_at'))->dateTime(Display::DATE_TIME)
                    ->placeholder(self::field('draft')),
                TextEntry::make('title')->label(self::field('title'))->columnSpanFull(),
                TextEntry::make('body_markdown')->label(self::field('body'))->markdown()->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(self::field('code'))
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state)),
                TextColumn::make('locale')->label(self::field('locale')),
                TextColumn::make('version')->label(self::field('version'))->searchable(),
                TextColumn::make('title')->label(self::field('title'))->searchable()->limit(50),
                TextColumn::make('state')->label(self::field('status'))->badge()
                    ->state(static fn (LegalDocument $record): string => $record->published_at === null ? 'draft' : 'published')
                    ->formatStateUsing(static fn (string $state): string => self::field($state))
                    ->color(static fn (string $state): string => Display::color($state)),
                TextColumn::make('published_at')->label(self::field('published_at'))->dateTime(Display::DATE_TIME)->placeholder('—')->sortable(),
                TextColumn::make('updated_at')->label(self::field('updated_at'))->dateTime(Display::DATE_TIME)->sortable(),
            ])
            ->filters([
                SelectFilter::make('code')->label(self::field('code'))->options(Display::options(LegalDocumentCode::class)),
                SelectFilter::make('locale')->label(self::field('locale'))
                    ->options(['ar' => Lang::get('common.locale_ar'), 'en' => Lang::get('common.locale_en')]),
                TernaryFilter::make('published_at')->label(self::field('published'))->nullable(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make(), self::publishAction()])
            ->defaultSort('updated_at', 'desc');
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->label(Lang::get('resources.legal_documents.actions.publish'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->visible(static fn (LegalDocument $record): bool => $record->published_at === null)
            ->requiresConfirmation()
            ->modalDescription(Lang::get('resources.legal_documents.actions.publish_help'))
            ->schema([
                DateTimePicker::make('at')->label(self::field('publish_at'))->seconds(false)
                    ->helperText(self::field('publish_at_help')),
            ])
            ->action(static fn (LegalDocument $record, array $data) => ModuleAction::run(
                static fn () => app(PublishLegalDocument::class)->handle(
                    $record,
                    AdminActor::current(),
                    filled($data['at'] ?? null) ? CarbonImmutable::parse((string) $data['at'], 'UTC') : null,
                ),
                Lang::get('resources.legal_documents.notifications.published'),
            ));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalDocuments::route('/'),
            'create' => CreateLegalDocument::route('/create'),
            'view' => ViewLegalDocument::route('/{record}'),
            'edit' => EditLegalDocument::route('/{record}/edit'),
        ];
    }
}
