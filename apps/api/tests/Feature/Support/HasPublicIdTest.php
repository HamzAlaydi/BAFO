<?php

declare(strict_types=1);

use App\Support\Database\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PublicIdProbe extends Model
{
    use HasPublicId;

    protected $table = 'public_id_probes';

    public $timestamps = false;

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('public_id_probes', function (Blueprint $table) {
        $table->id();
        $table->ulid('public_id')->unique();
        $table->string('name');
    });
});

it('assigns a lowercase ULID on create', function () {
    $probe = PublicIdProbe::create(['name' => 'a']);

    expect($probe->public_id)
        ->toBeString()
        ->toHaveLength(26)
        ->toBe(strtolower($probe->public_id))
        ->and(Str::isUlid($probe->public_id))->toBeTrue()
        ->and($probe->getRouteKey())->toBe($probe->public_id);
});

it('keeps an explicitly provided public id', function () {
    $id = PublicIdProbe::newPublicId();

    expect(PublicIdProbe::create(['name' => 'a', 'public_id' => $id])->public_id)->toBe($id);
});

it('finds by public id in any casing and rejects malformed ids', function () {
    $probe = PublicIdProbe::create(['name' => 'a']);

    expect(PublicIdProbe::findByPublicId(strtoupper($probe->public_id))?->is($probe))->toBeTrue()
        ->and(PublicIdProbe::findByPublicId('not-a-ulid'))->toBeNull()
        ->and((new PublicIdProbe)->resolveRouteBinding(strtoupper($probe->public_id))?->is($probe))->toBeTrue()
        ->and((new PublicIdProbe)->resolveRouteBinding('123'))->toBeNull();

    PublicIdProbe::findByPublicIdOrFail(PublicIdProbe::newPublicId());
})->throws(ModelNotFoundException::class);
