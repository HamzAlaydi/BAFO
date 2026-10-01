<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Support;

use App\Support\Exceptions\ApiException;
use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Runs a module Action from the panel (ARCHITECTURE §16). A domain refusal (ApiException, a
 * validation error of the Action) becomes a danger notification with the localised message and
 * halts the Filament action, so its modal stays open with the admin's input. Success can send a
 * notification.
 */
final class ModuleAction
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function run(Closure $callback, ?string $successTitle = null): mixed
    {
        try {
            $result = $callback();
        } catch (ApiException $exception) {
            self::fail($exception->translatedMessage(), self::firstMessage($exception->errors));
        } catch (ValidationException $exception) {
            self::fail(Lang::get('notifications.failed'), self::firstMessage($exception->errors()));
        } catch (InvalidArgumentException $exception) {
            report($exception);
            self::fail(Lang::get('notifications.failed'), null);
        }

        if ($successTitle !== null) {
            Notification::make()->success()->title($successTitle)->send();
        }

        return $result;
    }

    /**
     * A plain success notification (for outcomes decided after the Action returned).
     */
    public static function notify(string $title, ?string $body = null): void
    {
        Notification::make()->success()->title($title)->body($body)->send();
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private static function firstMessage(array $errors): ?string
    {
        foreach ($errors as $messages) {
            foreach ($messages as $message) {
                return $message;
            }
        }

        return null;
    }

    private static function fail(string $title, ?string $body): never
    {
        Notification::make()->danger()->title($title)->body($body)->persistent()->send();

        throw new Halt;
    }
}
