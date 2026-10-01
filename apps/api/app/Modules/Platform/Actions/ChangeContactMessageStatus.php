<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Enums\ContactStatus;
use App\Modules\Platform\Models\ContactMessage;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Admin contact inbox: marks a message read or archived (or new again). The acting admin is
 * recorded as the handler.
 */
final class ChangeContactMessageStatus
{
    public function handle(ContactMessage $message, ContactStatus $status, Actor $actor): ContactMessage
    {
        return DB::transaction(static function () use ($message, $status, $actor): ContactMessage {
            $message->status = $status;
            $message->handled_by_admin_id = $actor->adminId ?? $message->handled_by_admin_id;

            if ($message->isDirty()) {
                $message->save();
                AuditLogger::log('contact_message.updated', $message, AuditLogger::diff($message, ['status']), actor: $actor);
            }

            return $message;
        });
    }
}
