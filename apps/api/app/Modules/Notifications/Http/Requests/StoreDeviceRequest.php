<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Data\DeviceRegistration;
use App\Modules\Notifications\Enums\DevicePlatform;
use App\Modules\Notifications\Support\Locales;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * `POST /devices` (API.md §1.8).
 */
final class StoreDeviceRequest extends FormRequest
{
    /** Semantic version (semver.org), e.g. `1.0.0` or `1.2.0-beta.1`. */
    public const string SEMVER = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|ValidationRule|Enum>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
            // CONTRACT-GAP: API.md lists `platform` as in:ios,android,web without "required";
            // `device_tokens.platform` is NOT NULL, so it is required.
            'platform' => ['required', 'string', Rule::enum(DevicePlatform::class)],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:20', 'regex:'.self::SEMVER],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['token', 'platform', 'device_name', 'app_version'] as $field) {
            $label = __('notifications.attributes.'.$field);
            $attributes[$field] = is_string($label) ? $label : $field;
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = __('notifications.validation.semver');

        return is_string($message) ? ['app_version.regex' => $message] : [];
    }

    /**
     * CONTRACT-GAP: `device_tokens.locale` is not an input; it is the request language
     * (`Accept-Language`), i.e. the language the app runs in.
     */
    public function registration(): DeviceRegistration
    {
        $deviceName = $this->input('device_name');
        $appVersion = $this->input('app_version');

        return new DeviceRegistration(
            token: $this->string('token')->toString(),
            platform: DevicePlatform::from($this->string('platform')->toString()),
            deviceName: is_string($deviceName) && $deviceName !== '' ? $deviceName : null,
            appVersion: is_string($appVersion) && $appVersion !== '' ? $appVersion : null,
            locale: Locales::current(),
        );
    }
}
