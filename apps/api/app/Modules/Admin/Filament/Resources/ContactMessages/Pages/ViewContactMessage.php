<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ContactMessages\Pages;

use App\Modules\Admin\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Platform\Actions\ChangeContactMessageStatus;
use App\Modules\Platform\Enums\ContactStatus;
use App\Modules\Platform\Models\ContactMessage;
use Filament\Resources\Pages\ViewRecord;

/**
 * A contact message. Opening a new message marks it read (Platform `ChangeContactMessageStatus`).
 */
final class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $message = $this->getRecord();

        if ($message instanceof ContactMessage && $message->status === ContactStatus::New) {
            app(ChangeContactMessageStatus::class)->handle($message, ContactStatus::Read, AdminActor::current());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ContactMessageResource::statusAction()->after(fn () => $this->getRecord()->refresh()),
        ];
    }
}
