<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Notifications\Models\DeviceToken;
use App\Support\Database\Concerns\HasPublicId;
use App\Support\Files\File;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person using BAFO (ARCHITECTURE §5.3 `users`). Web and mobile authenticate with Sanctum
 * personal access tokens (D12). v1: exactly one membership per user.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $password
 * @property CarbonImmutable|null $email_verified_at
 * @property string $locale
 * @property int|null $avatar_file_id
 * @property UserStatus $status
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicId, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'email_verified_at',
        'locale',
        'avatar_file_id',
        'status',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'ar',
        'status' => 'active',
    ];

    /**
     * Mail and push are rendered in the user's language (ARCHITECTURE §11.1).
     */
    public function preferredLocale(): string
    {
        return $this->locale;
    }

    /**
     * The organization permissions of this user (ARCHITECTURE §8.1): none without an active
     * membership.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        $membership = $this->membership;

        if ($membership === null || $membership->status !== MembershipStatus::Active) {
            return [];
        }

        return OrgRole::permissions($membership);
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * The organization an e-mail address provably belongs to (SECURITY_REVIEW S-12): the one of
     * an active user who verified that address, through an active membership of an active
     * organization. A pending team member (the address owner never accepted) proves nothing.
     */
    public static function provenOrganizationIdFor(string $email): ?int
    {
        $user = self::query()->with('membership.organization')->where('email', mb_strtolower(trim($email)))->first();
        $membership = $user?->membership;

        if ($user === null || ! $user->isActive() || ! $user->hasVerifiedEmail()
            || $membership === null || $membership->status !== MembershipStatus::Active
            || $membership->organization === null || ! $membership->organization->isActive()) {
            return null;
        }

        return $membership->organization_id;
    }

    /**
     * @return HasOne<Membership, $this>
     */
    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    /**
     * @return HasOneThrough<Organization, Membership, $this>
     */
    public function organization(): HasOneThrough
    {
        return $this->hasOneThrough(Organization::class, Membership::class, 'user_id', 'id', 'id', 'organization_id');
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function avatarFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'avatar_file_id');
    }

    /**
     * @return HasMany<OtpCode, $this>
     */
    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class);
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @return HasMany<AccountDeletionRequest, $this>
     */
    public function accountDeletionRequests(): HasMany
    {
        return $this->hasMany(AccountDeletionRequest::class);
    }

    /**
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
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
            'password' => 'hashed',
            'email_verified_at' => 'immutable_datetime',
            'status' => UserStatus::class,
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
