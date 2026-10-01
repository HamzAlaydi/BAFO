<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * What a notification is about: `{type, id}` in `notifications.data.subject` (ARCHITECTURE §11.2),
 * where `type` is the morph alias (§4.9) and `id` the public id.
 */
final readonly class NotificationSubject
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}

    /**
     * A model with a morph alias and a `public_id`.
     */
    public static function of(Model $model): self
    {
        return new self($model->getMorphClass(), (string) $model->getAttribute('public_id'));
    }

    /**
     * @return array{type: string, id: string}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
