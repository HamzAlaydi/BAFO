<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One runtime setting (table `app_settings`, ARCHITECTURE §4.7, §15.3). Read and write it
 * through Settings, which caches the table and knows the registered defaults.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property int|null $updated_by_admin_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class AppSetting extends Model
{
    protected $table = 'app_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'updated_by_admin_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // JSONB holding any JSON value: scalars, lists and objects.
        return [
            'value' => 'json',
        ];
    }
}
