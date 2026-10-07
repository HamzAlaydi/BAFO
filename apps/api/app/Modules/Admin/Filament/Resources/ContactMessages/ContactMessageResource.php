<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ContactMessages;

use App\Modules\Admin\Enums\AdminNavigationGroup;
use App\Modules\Admin\Enums\OpsSurface;
use App\Modules\Admin\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Modules\Admin\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Modules\Admin\Filament\Support\AdminResource;
use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Admin\Filament\Support\ModuleAction;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Platform\Actions\ChangeContactMessageStatus;
use App\Modules\Platform\Enums\ContactStatus;
use App\Modules\Platform\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * §16 Contact inbox: list, view, change status (Platform `ChangeContactMessageStatus`). Opening a
 * new message marks it read.
 */
final class ContactMessageResource extends AdminResource
{
    protected static ?string $model = ContactMessage::class;

    protected static string $langKey = 'contact_messages';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    protected static ?OpsSurface $opsSurface = OpsSurface::ContactMessages;

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getNavigationBadge(): ?string
    {
        $new = ContactMessage::query()->where('status', ContactStatus::New->value)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->label(self::field('subject'))->searchable()->limit(50)
                    ->weight(static fn (ContactMessage $record): ?string => $record->status === ContactStatus::New ? 'bold' : null),
                TextColumn::make('name')->label(self::field('name'))->searchable(),
                TextColumn::make('email')->label(self::field('email'))->searchable(),
                TextColumn::make('company')->label(self::field('company'))->placeholder('—')->toggleable(),
                TextColumn::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextColumn::make('created_at')->label(self::field('received_at'))->dateTime(Display::DATE_TIME)->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(self::field('status'))->options(Display::options(ContactStatus::class)),
            ])
            ->recordActions([ViewAction::make(), self::statusAction()])
            ->defaultSort('created_at', 'desc');
    }

    public static function statusAction(): Action
    {
        return Action::make('changeStatus')
            ->label(Lang::get('resources.contact_messages.actions.change_status'))
            ->icon(Heroicon::OutlinedInboxArrowDown)
            ->fillForm(static fn (ContactMessage $record): array => ['status' => $record->status->value])
            ->schema([
                Select::make('status')->label(self::field('status'))->required()->options(Display::options(ContactStatus::class)),
            ])
            ->action(static fn (ContactMessage $record, array $data) => ModuleAction::run(
                static fn () => app(ChangeContactMessageStatus::class)->handle($record, ContactStatus::from((string) $data['status']), AdminActor::current()),
                Lang::get('notifications.saved'),
            ));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('subject')->label(self::field('subject'))->columnSpanFull(),
                TextEntry::make('name')->label(self::field('name')),
                TextEntry::make('email')->label(self::field('email'))->copyable(),
                TextEntry::make('phone')->label(self::field('phone'))->placeholder('—'),
                TextEntry::make('company')->label(self::field('company'))->placeholder('—'),
                TextEntry::make('locale')->label(self::field('locale')),
                TextEntry::make('status')->label(self::field('status'))->badge()
                    ->formatStateUsing(static fn (mixed $state): ?string => Display::enum($state))
                    ->color(static fn (mixed $state): string => Display::color($state)),
                TextEntry::make('message')->label(self::field('message'))->columnSpanFull()->prose(),
                TextEntry::make('created_at')->label(self::field('received_at'))->dateTime(Display::DATE_TIME),
                TextEntry::make('ip')->label(self::field('ip'))->placeholder('—'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
