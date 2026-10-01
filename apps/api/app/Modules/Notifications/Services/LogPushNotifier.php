<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Contracts\PushNotifier;
use App\Modules\Notifications\Data\PushMessage;
use App\Modules\Notifications\Models\DeviceToken;
use Illuminate\Log\LogManager;
use Illuminate\Support\Collection;

/**
 * The `log` push driver (ARCHITECTURE §15.1): writes the title, body, data and token count to
 * the `push` log channel. Device tokens are never logged.
 */
final readonly class LogPushNotifier implements PushNotifier
{
    public function __construct(
        private LogManager $log,
        private string $channel = 'push',
    ) {}

    public function send(Collection $tokens, PushMessage $message): void
    {
        $this->log->channel($this->channel)->info('push.sent', [
            'title' => $message->title,
            'body' => $message->body,
            'data' => $message->data,
            'token_count' => $tokens->count(),
            'platforms' => $tokens->countBy(static fn (DeviceToken $token): string => $token->platform->value)->all(),
        ]);
    }
}
