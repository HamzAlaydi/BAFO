<?php

declare(strict_types=1);

use App\Modules\Platform\Models\LegalDocument;
use App\Support\Database\MorphMap;
use App\Support\Files\File;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

it('requires a morph alias for every polymorphic model', function () {
    expect(Relation::requiresMorphMap())->toBeTrue();

    (new class extends Model {})->getMorphClass();
})->throws(ClassMorphViolationException::class);

it('registers the complete §4.9 catalogue', function (string $alias, string $class) {
    expect(Relation::getMorphedModel($alias))->toBe($class);
})->with([
    ['user', 'App\\Modules\\Identity\\Models\\User'],
    ['organization', 'App\\Modules\\Identity\\Models\\Organization'],
    ['membership', 'App\\Modules\\Identity\\Models\\Membership'],
    ['vendor', 'App\\Modules\\Integrations\\Models\\Vendor'],
    ['api_client', 'App\\Modules\\Integrations\\Models\\ApiClient'],
    ['webhook_endpoint', 'App\\Modules\\Integrations\\Models\\WebhookEndpoint'],
    ['competition', 'App\\Modules\\Competitions\\Models\\Competition'],
    ['invitation', 'App\\Modules\\Competitions\\Models\\Invitation'],
    ['participant', 'App\\Modules\\Competitions\\Models\\Participant'],
    ['comment', 'App\\Modules\\Competitions\\Models\\Comment'],
    ['competition_attachment', 'App\\Modules\\Competitions\\Models\\CompetitionAttachment'],
    ['offer', 'App\\Modules\\Bidding\\Models\\Offer'],
    ['award', 'App\\Modules\\Bidding\\Models\\Award'],
    ['bafo_round', 'App\\Modules\\Bidding\\Models\\BafoRound'],
    ['payment', 'App\\Modules\\Billing\\Models\\Payment'],
    ['subscription', 'App\\Modules\\Billing\\Models\\Subscription'],
    ['invoice', 'App\\Modules\\Billing\\Models\\Invoice'],
    ['coupon', 'App\\Modules\\Billing\\Models\\Coupon'],
    ['competition_sponsorship', 'App\\Modules\\Billing\\Models\\CompetitionSponsorship'],
    ['sponsored_pass', 'App\\Modules\\Billing\\Models\\SponsoredPass'],
    ['admin', 'App\\Modules\\Admin\\Models\\Admin'],
    ['file', 'App\\Support\\Files\\File'],
    ['legal_document', 'App\\Modules\\Platform\\Models\\LegalDocument'],
    ['contact_message', 'App\\Modules\\Platform\\Models\\ContactMessage'],
]);

it('maps Platform models to their aliases', function () {
    expect((new File)->getMorphClass())->toBe('file')
        ->and((new LegalDocument)->getMorphClass())->toBe('legal_document')
        ->and(count(MorphMap::ALIASES))->toBe(24);
});
