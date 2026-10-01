<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Enums\ContactStatus;
use App\Modules\Platform\Models\ContactMessage;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * Stores a contact form message for the admin inbox (POST /contact).
 */
final class SubmitContactMessage
{
    /**
     * @param  array{name: string, email: string, phone: string|null, company: string|null, subject: string, message: string}  $data
     */
    public function handle(array $data, Actor $actor): ContactMessage
    {
        return DB::transaction(static function () use ($data, $actor): ContactMessage {
            $message = ContactMessage::query()->create([
                ...$data,
                'email' => mb_strtolower($data['email']),
                'locale' => App::getLocale(),
                'status' => ContactStatus::New,
                'ip' => $actor->ip,
                'user_agent' => $actor->userAgent,
            ]);

            AuditLogger::log('contact_message.received', $message, actor: $actor);

            return $message;
        });
    }
}
