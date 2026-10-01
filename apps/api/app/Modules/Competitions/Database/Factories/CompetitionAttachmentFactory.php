<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Database\Factories;

use App\Modules\Competitions\Enums\AttachmentKind;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A PDF document (terms and specifications) of a competition, with its `files` row.
 *
 * @extends Factory<CompetitionAttachment>
 */
final class CompetitionAttachmentFactory extends Factory
{
    protected $model = CompetitionAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'kind' => AttachmentKind::Document,
            'file_id' => static fn (array $attributes): int => File::factory()
                ->purpose(FilePurpose::CompetitionAttachment)
                ->create([
                    'organization_id' => Competition::query()->findOrFail($attributes['competition_id'])->organization_id,
                    'original_name' => 'كراسة الشروط والمواصفات.pdf',
                ])
                ->id,
            'title' => 'كراسة الشروط والمواصفات',
            'url' => null,
            'is_addendum' => false,
            'uploaded_by_user_id' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Also visible on the invitee teaser (§8.5).
     */
    public function invitationDocument(): self
    {
        return $this->state(['kind' => AttachmentKind::InvitationDocument, 'title' => 'خطاب الدعوة']);
    }

    public function externalLink(): self
    {
        return $this->state([
            'kind' => AttachmentKind::ExternalLink,
            'file_id' => null,
            'title' => 'المخططات الهندسية',
            'url' => 'https://drive.example.sa/specs/'.$this->faker->uuid(),
        ]);
    }

    public function addendum(): self
    {
        return $this->state(['is_addendum' => true, 'title' => 'ملحق رقم 1']);
    }
}
