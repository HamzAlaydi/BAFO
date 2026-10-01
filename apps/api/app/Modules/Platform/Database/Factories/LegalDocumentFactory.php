<?php

declare(strict_types=1);

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<LegalDocument>
 */
final class LegalDocumentFactory extends Factory
{
    protected $model = LegalDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => LegalDocumentCode::Terms,
            'locale' => 'ar',
            'version' => '2026-10-01',
            'title' => 'الشروط والأحكام',
            'body_markdown' => "# Terms\n\n".fake()->paragraph(),
            'published_at' => Date::now()->subDay(),
        ];
    }

    public function draft(): self
    {
        return $this->state(['published_at' => null]);
    }

    public function forCode(LegalDocumentCode $code, string $locale, string $version = '2026-10-01'): self
    {
        return $this->state(['code' => $code, 'locale' => $locale, 'version' => $version]);
    }
}
