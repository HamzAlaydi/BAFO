<?php

declare(strict_types=1);

namespace App\Modules\Platform\Enums;

/**
 * The legal documents (legal_documents.code, consents.document_code; ARCHITECTURE §5.1).
 */
enum LegalDocumentCode: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
    case Refund = 'refund';
    case CompetitionRules = 'competition_rules';
    case ApiTerms = 'api_terms';

    /**
     * The documents whose current version GET /app-config announces (API.md §2.13).
     *
     * @return list<self>
     */
    public static function announcedInAppConfig(): array
    {
        return [self::Terms, self::Privacy, self::CompetitionRules];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $code): string => $code->value, self::cases());
    }

    public function label(?string $locale = null): string
    {
        $label = __('platform.enums.legal_document_code.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }
}
