<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;
use App\Support\Auth\ActorType;
use App\Support\Auth\Channel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

it('builds the system actor with the propagated request id', function () {
    Context::add('request_id', 'job-trace-0001');

    $actor = Actor::system();

    expect($actor->type)->toBe(ActorType::System)
        ->and($actor->channel)->toBe(Channel::System)
        ->and($actor->id)->toBeNull()
        ->and($actor->organizationId)->toBeNull()
        ->and($actor->requestId)->toBe('job-trace-0001')
        ->and($actor->isSystem())->toBeTrue();
});

it('builds an API client actor from the client model', function () {
    $client = new class extends Model {};
    $client->forceFill(['id' => 12, 'organization_id' => 4, 'name' => 'SAP connector']);
    $request = Request::create('/api/public/v1/vendors', server: ['REMOTE_ADDR' => '10.1.1.1']);

    $actor = Actor::forApiClient($client, $request);

    expect($actor->type)->toBe(ActorType::ApiClient)
        ->and($actor->id)->toBe(12)
        ->and($actor->apiClientId)->toBe(12)
        ->and($actor->userId)->toBeNull()
        ->and($actor->organizationId)->toBe(4)
        ->and($actor->channel)->toBe(Channel::Api)
        ->and($actor->label)->toBe('SAP connector')
        ->and($actor->ip)->toBe('10.1.1.1')
        ->and($actor->isApiClient())->toBeTrue();
});

it('builds an admin actor without an organization', function () {
    $admin = new class extends Model {};
    $admin->forceFill(['id' => 3, 'email' => 'ops@bafo.test']);

    $actor = Actor::forAdmin($admin);

    expect($actor->type)->toBe(ActorType::Admin)
        ->and($actor->adminId)->toBe(3)
        ->and($actor->organizationId)->toBeNull()
        ->and($actor->channel)->toBe(Channel::Admin)
        ->and($actor->label)->toBe('ops@bafo.test');
});

it('takes the organization of an Identity user from the membership', function () {
    $user = new User;
    $user->forceFill(['id' => 21, 'name' => 'سارة', 'email' => 'sara@example.test']);
    $user->exists = true;
    $user->setRelation('membership', (new Membership)->forceFill(['organization_id' => 8]));

    $actor = Actor::forUser($user, Request::create('/', server: ['HTTP_X_PLATFORM' => 'ios']));

    expect($actor->type)->toBe(ActorType::User)
        ->and($actor->userId)->toBe(21)
        ->and($actor->organizationId)->toBe(8)
        ->and($actor->channel)->toBe(Channel::Ios)
        ->and($actor->label)->toBe('سارة');
});
