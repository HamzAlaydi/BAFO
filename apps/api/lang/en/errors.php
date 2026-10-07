<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API error messages
|--------------------------------------------------------------------------
|
| The key is the "code" of the error body { message, code, errors }.
| Every new code is added here and in lang/ar/errors.php together.
|
*/

return [
    'bad_request' => 'The request is invalid.',
    'unauthenticated' => 'You must be signed in to continue.',
    'forbidden' => 'You are not allowed to perform this action.',
    'not_found' => 'The requested resource was not found.',
    'method_not_allowed' => 'This method is not supported for this route.',
    'not_acceptable' => 'The requested response format is not supported.',
    'conflict' => 'The request conflicts with the current state of the resource.',
    'gone' => 'This resource is no longer available.',
    'payload_too_large' => 'The request is larger than allowed.',
    'unsupported_media_type' => 'The content type is not supported.',
    'session_expired' => 'Your session has expired. Refresh the page and try again.',
    'validation_failed' => 'The given data is invalid.',
    'too_many_requests' => 'Too many requests. Please try again shortly.',
    'server_error' => 'Something went wrong. Please try again later.',
    'service_unavailable' => 'The service is temporarily unavailable. Please try again later.',
    'maintenance' => 'The platform is under maintenance. Please try again later.',
    'http_error' => 'The request could not be completed.',
    'app_version_unsupported' => 'This version of the app is no longer supported. Please update the app to continue.',
    'idempotency_key_required' => 'A valid Idempotency-Key header is required for this request.',
    'idempotency_key_reused' => 'This Idempotency-Key was already used for a different request.',
    'idempotency_request_in_progress' => 'A request with this Idempotency-Key is still being processed. Please try again shortly.',
    'invalid_state_transition' => 'This action is not allowed in the current state.',
    'file_type_not_allowed' => 'This file type is not allowed. Allowed types: :extensions.',
    'file_too_large' => 'The file is too large. The maximum size is :max MB.',
    // Release scope (RELEASE_SCOPE.md §1.5): the route gate and the field-level refusal.
    'feature_disabled' => 'This feature is not available in this release.',
    'feature_disabled_field' => 'This option is not available in this release.',
];
