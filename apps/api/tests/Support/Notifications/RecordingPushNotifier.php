<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use App\Modules\Notifications\Contracts\PushNotifier;
use App\Modules\Notifications\Data\PushMessage;
use App\Modules\Notifications\Models\DeviceToken;
use Illuminate\Support\Collection;

/**
 * A PushNotifier that records what it is asked to send.
 *
 *     $push = RecordingPushNotifier::swap();
 *     ...
 *     expect($push->sent)->toHaveCount(1);
 */
final class RecordingPushNotifier implements PushNotifier
{
    /** @var list<array{tokens: list<string>, message: PushMessage}> */
    public array $sent = [];

    public static function swap(): self
    {
        $fake = new self;
        app()->instance(PushNotifier::class, $fake);

        return $fake;
    }

    public function send(Collection $tokens, PushMessage $message): void
    {
        $this->sent[] = [
            'tokens' => array_values($tokens->map(static fn (DeviceToken $token): string => $token->token)->all()),
            'message' => $message,
        ];
    }
}
