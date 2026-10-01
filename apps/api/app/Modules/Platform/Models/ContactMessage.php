<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Database\Factories\ContactMessageFactory;
use App\Modules\Platform\Enums\ContactStatus;
use App\Support\Database\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A message sent through the public contact form (POST /contact), read in the admin inbox.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $company
 * @property string $subject
 * @property string $message
 * @property string $locale
 * @property ContactStatus $status
 * @property string|null $ip
 * @property string|null $user_agent
 * @property int|null $handled_by_admin_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    use HasPublicId;

    protected $table = 'contact_messages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'subject',
        'message',
        'locale',
        'status',
        'ip',
        'user_agent',
        'handled_by_admin_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    protected static function newFactory(): ContactMessageFactory
    {
        return ContactMessageFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
        ];
    }
}
