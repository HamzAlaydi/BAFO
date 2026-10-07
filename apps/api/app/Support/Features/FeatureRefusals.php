<?php

declare(strict_types=1);

namespace App\Support\Features;

use App\Support\Exceptions\ApiException;
use Illuminate\Validation\Validator;

/**
 * The two answers of RELEASE_SCOPE.md §1.5 for a feature the release scope hides:
 *
 *   throw FeatureRefusals::disabled(Feature::Coupons);          404 `feature_disabled`, details.feature
 *   FeatureRefusals::refuseField($validator, 'format');         422 `validation_failed`, the shared
 *                                                                `errors.feature_disabled_field` message on the path
 *
 * Used by the `feature:` route middleware and by the FormRequests (`after()` hooks or
 * `prepareForValidation()`) that refuse a hidden value of an otherwise visible endpoint.
 */
final class FeatureRefusals
{
    public static function disabled(Feature|string $feature): ApiException
    {
        $name = $feature instanceof Feature ? $feature->value : $feature;

        return new ApiException('feature_disabled', status: 404, details: ['feature' => $name]);
    }

    /**
     * Adds the field-level refusal on `$path`, once (a path that already failed its own rule keeps
     * that message only).
     */
    public static function refuseField(Validator $validator, string $path): void
    {
        if ($validator->errors()->has($path)) {
            return;
        }

        $message = __('errors.feature_disabled_field');

        $validator->errors()->add($path, is_string($message) ? $message : 'feature_disabled_field');
    }
}
