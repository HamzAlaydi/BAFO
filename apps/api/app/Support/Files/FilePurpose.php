<?php

declare(strict_types=1);

namespace App\Support\Files;

/**
 * What a stored file is for (ARCHITECTURE §4.6, files.purpose). The purpose fixes the disk,
 * the allowed extensions and the size limit, and selects the FileAccessRegistry rule.
 */
enum FilePurpose: string
{
    case OrganizationLogo = 'organization_logo';
    case OrganizationProfile = 'organization_profile';
    case UserAvatar = 'user_avatar';
    case CompetitionAttachment = 'competition_attachment';
    case InvoicePdf = 'invoice_pdf';
    case CompetitionReport = 'competition_report';
    case ImportSource = 'import_source';
    case ImportErrors = 'import_errors';
    case Export = 'export';

    private const int MB = 1024 * 1024;

    public function disk(): string
    {
        return $this->isPublic() ? 'public' : 'private';
    }

    /**
     * Logos and avatars live on the public disk under unguessable ULID paths (D11).
     */
    public function isPublic(): bool
    {
        return $this === self::OrganizationLogo || $this === self::UserAvatar;
    }

    /**
     * Files the platform generates itself (storeContents); they are never uploaded.
     */
    public function isGenerated(): bool
    {
        return in_array($this, [self::InvoicePdf, self::CompetitionReport, self::ImportErrors, self::Export], true);
    }

    /**
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return match ($this) {
            self::OrganizationLogo, self::UserAvatar => ['png', 'jpg', 'jpeg', 'webp'],
            self::OrganizationProfile, self::InvoicePdf, self::CompetitionReport => ['pdf'],
            self::CompetitionAttachment => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'zip'],
            self::ImportSource, self::ImportErrors, self::Export => ['csv', 'xlsx'],
        };
    }

    public function label(?string $locale = null): string
    {
        $label = __('platform.enums.file_purpose.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }

    /**
     * Upload size limit in bytes; null for generated files (no limit).
     */
    public function maxBytes(): ?int
    {
        return match ($this) {
            self::OrganizationLogo, self::UserAvatar => 2 * self::MB,
            self::OrganizationProfile, self::ImportSource => 20 * self::MB,
            self::CompetitionAttachment => 100 * self::MB,
            self::InvoicePdf, self::CompetitionReport, self::ImportErrors, self::Export => null,
        };
    }
}
