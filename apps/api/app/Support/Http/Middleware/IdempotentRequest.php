<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use App\Support\Auth\ActorType;
use App\Support\Auth\CurrentActor;
use App\Support\Exceptions\ApiException;
use App\Support\Idempotency\IdempotencyKey;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Alias `idempotent` (ARCHITECTURE §4.8): public API POSTs that create or act, and checkout.
 *
 *   ->middleware('idempotent')            Idempotency-Key required (400 idempotency_key_required)
 *   ->middleware('idempotent:optional')   without the header the request runs normally
 *
 * Keys (8–64 chars `[A-Za-z0-9_-]`) are scoped to the caller (user or API client, from
 * CurrentActor, so the middleware runs after authentication). The first request claims the
 * key; a replay with the same method, path and body gets the stored status and body plus
 * `Idempotent-Replayed: true`; a different request with the key → 422
 * idempotency_key_reused; a replay while the first one runs → 409
 * idempotency_request_in_progress. Rows live 24 h.
 *
 * CONTRACT-GAP: responses with status ≥ 500 or 429 (and exceptions) are not stored: the key is
 * released so the client can retry the same intent. Every other status is stored and replayed.
 *
 * The stored body is encrypted at rest (`{"sealed": "<Crypt>"}` in the jsonb column), because
 * some responses show a secret once (webhook endpoint secrets, SECURITY_REVIEW S-08). Rows written
 * before that (plain JSON) are still replayed as they are.
 */
final class IdempotentRequest
{
    public const string HEADER = 'Idempotency-Key';

    public const string REPLAYED_HEADER = 'Idempotent-Replayed';

    private const string KEY_PATTERN = '/^[A-Za-z0-9_-]{8,64}$/';

    private const string SEALED = 'sealed';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $key = trim((string) $request->header(self::HEADER, ''));

        if ($key === '' && $mode === 'optional') {
            return $next($request);
        }

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new ApiException('idempotency_key_required', status: 400);
        }

        [$scopeType, $scopeId] = $this->scope();
        $method = $request->getMethod();
        $path = mb_substr('/'.ltrim($request->path(), '/'), 0, 255);
        $hash = hash('sha256', $method."\n".$path."\n".$request->getContent());

        $existing = $this->claim($scopeType, $scopeId, $key, $method, $path, $hash);

        if ($existing !== null) {
            return $this->answerExisting($existing, $hash);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->release($scopeType, $scopeId, $key);

            throw $e;
        }

        $this->complete($scopeType, $scopeId, $key, $response);

        return $response;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function scope(): array
    {
        $actor = CurrentActor::get();

        return match (true) {
            $actor->type === ActorType::User && $actor->userId !== null => [IdempotencyKey::SCOPE_USER, $actor->userId],
            $actor->type === ActorType::ApiClient && $actor->apiClientId !== null => [IdempotencyKey::SCOPE_API_CLIENT, $actor->apiClientId],
            default => throw new AuthenticationException,
        };
    }

    /**
     * Inserts the key as "in progress". Returns null when this request now owns it, or the
     * live row another request created.
     */
    private function claim(string $scopeType, int $scopeId, string $key, string $method, string $path, string $hash, int $attempt = 1): ?IdempotencyKey
    {
        $now = Date::now();

        $inserted = IdempotencyKey::query()->insertOrIgnore([
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'key' => $key,
            'method' => $method,
            'path' => $path,
            'request_hash' => $hash,
            'response_status' => null,
            'response_body' => null,
            'expires_at' => $now->addHours(IdempotencyKey::TTL_HOURS),
            'created_at' => $now,
        ]);

        if ($inserted === 1) {
            return null;
        }

        $existing = $this->find($scopeType, $scopeId, $key);

        // The row expired (not pruned yet) or vanished between the two statements: start over.
        if (($existing === null || $existing->expires_at->lessThanOrEqualTo($now)) && $attempt < 3) {
            $existing?->delete();

            return $this->claim($scopeType, $scopeId, $key, $method, $path, $hash, $attempt + 1);
        }

        return $existing ?? throw new ApiException('idempotency_request_in_progress', status: 409);
    }

    private function answerExisting(IdempotencyKey $existing, string $hash): Response
    {
        if (! hash_equals($existing->request_hash, $hash)) {
            throw new ApiException('idempotency_key_reused', status: 422);
        }

        if (! $existing->isCompleted()) {
            throw new ApiException('idempotency_request_in_progress', status: 409);
        }

        $headers = [self::REPLAYED_HEADER => 'true'];
        $body = self::unseal((string) $existing->getRawOriginal('response_body'));

        if ($body === '') {
            return new IlluminateResponse('', (int) $existing->response_status, $headers);
        }

        return new IlluminateResponse($body, (int) $existing->response_status, [...$headers, 'Content-Type' => 'application/json']);
    }

    private function complete(string $scopeType, int $scopeId, string $key, Response $response): void
    {
        $status = $response->getStatusCode();

        if ($status >= 500 || $status === 429) {
            $this->release($scopeType, $scopeId, $key);

            return;
        }

        $content = $response->getContent();
        $body = is_string($content) && $content !== '' && json_validate($content)
            ? json_encode([self::SEALED => Crypt::encryptString($content)], JSON_THROW_ON_ERROR)
            : null;

        $this->query($scopeType, $scopeId, $key)->update([
            'response_status' => $status,
            'response_body' => $body,
        ]);
    }

    /**
     * The original response body of a stored row: sealed rows are decrypted, older plain rows
     * are returned as they are.
     */
    private static function unseal(string $stored): string
    {
        if ($stored === '') {
            return '';
        }

        $decoded = json_decode($stored, true);

        if (is_array($decoded) && count($decoded) === 1 && is_string($decoded[self::SEALED] ?? null)) {
            return Crypt::decryptString($decoded[self::SEALED]);
        }

        return $stored;
    }

    private function release(string $scopeType, int $scopeId, string $key): void
    {
        $this->query($scopeType, $scopeId, $key)->whereNull('response_status')->delete();
    }

    private function find(string $scopeType, int $scopeId, string $key): ?IdempotencyKey
    {
        return $this->query($scopeType, $scopeId, $key)->first();
    }

    /**
     * @return Builder<IdempotencyKey>
     */
    private function query(string $scopeType, int $scopeId, string $key): Builder
    {
        return IdempotencyKey::query()
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('key', $key);
    }
}
