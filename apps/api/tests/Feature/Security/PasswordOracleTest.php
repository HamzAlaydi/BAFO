<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Tests\Support\Identity\Accounts;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-11: a stolen bearer token must not turn the
 * endpoints that confirm the current password (change password, request account deletion) into
 * a password-guessing oracle. They allow 5 attempts a minute per user.
 */

beforeEach(function () {
    Mail::fake();
});

it('limits current-password checks per user', function (string $method, string $uri, array $body) {
    $user = Accounts::owner();
    $headers = Accounts::headers($user);

    foreach (range(1, 5) as $ignored) {
        $this->json($method, $uri, $body, $headers)->assertUnprocessable()->assertJsonPath('code', 'password_incorrect');
    }

    $this->json($method, $uri, $body, $headers)->assertStatus(429);

    // Another user is not affected.
    $other = Accounts::owner();
    $this->json($method, $uri, $body, Accounts::headers($other))->assertUnprocessable();
})->with([
    'change password' => ['PUT', '/api/app/v1/me/password', ['current_password' => 'Wr0ng-Guess!', 'password' => Accounts::NEW_PASSWORD, 'password_confirmation' => Accounts::NEW_PASSWORD]],
    'account deletion' => ['POST', '/api/app/v1/account/deletion', ['password' => 'Wr0ng-Guess!']],
]);
