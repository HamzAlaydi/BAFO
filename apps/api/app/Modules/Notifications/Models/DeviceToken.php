<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Database\Factories\DeviceTokenFactory;
use App\Modules\Notifications\Enums\DevicePlatform;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A push registration (FCM token) of one device (ARCHITECTURE §5.8 `device_tokens`).
 *
 * In-app notifications use Laravel's `DatabaseNotification` model on the `notifications` table
 * (§5.8); no custom model is needed.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $token
 * @property DevicePlatform $platform
 * @property string|null $device_name
 * @property string|null $app_version
 * @property string $locale
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DeviceToken extends Model
{
    /** @use HasFactory<DeviceTokenFactory> */
    use HasFactory, HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_name',
        'app_version',
        'locale',
        'last_seen_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): DeviceTokenFactory
    {
        return DeviceTokenFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}
