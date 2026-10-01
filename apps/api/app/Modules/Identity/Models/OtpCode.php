<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\OtpCodeFactory;
use App\Modules\Identity\Enums\OtpPurpose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code (ARCHITECTURE §5.3 `otp_codes`, §13.9). Only the HMAC of the code is stored.
 * Internal: no public id.
 *
 * @property int $id
 * @property string $email
 * @property int|null $user_id
 * @property OtpPurpose $purpose
 * @property string $code_hash
 * @property array<string, mixed>|null $context
 * @property int $attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property string|null $ip
 * @property CarbonImmutable|null $created_at
 */
class OtpCode extends Model
{
    /** @use HasFactory<OtpCodeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'user_id',
        'purpose',
        'code_hash',
        'context',
        'attempts',
        'expires_at',
        'consumed_at',
        'ip',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'code_hash',
    ];

    /**
     * `hash_hmac('sha256', code, config('app.key'))` (ARCHITECTURE §5.3).
     */
    public static function hashCode(string $code): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $code, is_string($key) ? $key : '');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): OtpCodeFactory
    {
        return OtpCodeFactory::new();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: static fn (string $value): string => mb_strtolower(trim($value)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'context' => 'array',
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }
}
