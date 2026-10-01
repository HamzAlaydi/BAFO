<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\AppV1;

use App\Modules\Platform\Actions\SubmitContactMessage;
use App\Modules\Platform\Http\Requests\StoreContactMessageRequest;
use App\Modules\Platform\Http\Resources\ContactMessageResource;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/app/v1/contact (guest, `guest-forms` limiter): the public contact form.
 */
final class ContactMessageController extends ApiController
{
    public function store(StoreContactMessageRequest $request, SubmitContactMessage $submit): JsonResponse
    {
        $message = $submit->handle($request->contactData(), CurrentActor::get());

        return $this->created(new ContactMessageResource($message));
    }
}
