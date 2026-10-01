<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Morph aliases (ARCHITECTURE §4.9). Relation::requireMorphMap() is on, so every model used
 * polymorphically (audit subjects, Sanctum tokens, notifications, webhook subjects) must
 * have an alias; class names never reach the database.
 *
 * The contract assigns each alias to its module and each module may also register its own
 * in its provider's register() (§3.4). The kernel registers the complete catalogue once, by
 * class-string, so the aliases are stable from day one even for models that do not exist
 * yet; a module re-registering the same alias → class pair is harmless.
 */
final class MorphMap
{
    /**
     * @var array<string, string>
     */
    public const array ALIASES = [
        // Platform (kernel)
        // CONTRACT-GAP: §4.9 lists no Platform aliases; audit entries on files, legal documents
        // and contact messages need them.
        'file' => 'App\\Support\\Files\\File',
        'legal_document' => 'App\\Modules\\Platform\\Models\\LegalDocument',
        'contact_message' => 'App\\Modules\\Platform\\Models\\ContactMessage',

        // Identity
        'user' => 'App\\Modules\\Identity\\Models\\User',
        'organization' => 'App\\Modules\\Identity\\Models\\Organization',
        'membership' => 'App\\Modules\\Identity\\Models\\Membership',

        // Integrations
        'vendor' => 'App\\Modules\\Integrations\\Models\\Vendor',
        'api_client' => 'App\\Modules\\Integrations\\Models\\ApiClient',
        'webhook_endpoint' => 'App\\Modules\\Integrations\\Models\\WebhookEndpoint',

        // Competitions
        'competition' => 'App\\Modules\\Competitions\\Models\\Competition',
        'invitation' => 'App\\Modules\\Competitions\\Models\\Invitation',
        'participant' => 'App\\Modules\\Competitions\\Models\\Participant',
        'comment' => 'App\\Modules\\Competitions\\Models\\Comment',
        'competition_attachment' => 'App\\Modules\\Competitions\\Models\\CompetitionAttachment',

        // Bidding
        'offer' => 'App\\Modules\\Bidding\\Models\\Offer',
        'award' => 'App\\Modules\\Bidding\\Models\\Award',
        'bafo_round' => 'App\\Modules\\Bidding\\Models\\BafoRound',

        // Billing
        'payment' => 'App\\Modules\\Billing\\Models\\Payment',
        'subscription' => 'App\\Modules\\Billing\\Models\\Subscription',
        'invoice' => 'App\\Modules\\Billing\\Models\\Invoice',
        'coupon' => 'App\\Modules\\Billing\\Models\\Coupon',
        'competition_sponsorship' => 'App\\Modules\\Billing\\Models\\CompetitionSponsorship',
        'sponsored_pass' => 'App\\Modules\\Billing\\Models\\SponsoredPass',

        // Admin
        'admin' => 'App\\Modules\\Admin\\Models\\Admin',
    ];

    public static function register(): void
    {
        Relation::requireMorphMap();
        Relation::morphMap(self::ALIASES);
    }
}
