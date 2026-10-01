<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Database\Factories;

use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A Q&A clarification posted by the issuer (a member of the issuer organization) on a live
 * competition. `byParticipant()` makes it a participant question.
 *
 * @extends Factory<Comment>
 */
final class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory()->live(),
            'parent_id' => null,
            'author_organization_id' => static fn (array $attributes): int => Competition::query()
                ->findOrFail($attributes['competition_id'])
                ->organization_id,
            'author_user_id' => static fn (array $attributes): int => User::factory()
                ->withMembership(Organization::query()->findOrFail($attributes['author_organization_id']))
                ->create()
                ->id,
            'author_participant_id' => null,
            'is_issuer' => true,
            'body' => 'نود التنويه بأن مدة التوريد المطلوبة ثلاثون يوماً من تاريخ الترسية.',
        ];
    }

    public function byParticipant(Participant $participant): self
    {
        return $this->state([
            'competition_id' => $participant->competition_id,
            'author_organization_id' => $participant->organization_id,
            'author_user_id' => $participant->joined_by_user_id,
            'author_participant_id' => $participant->id,
            'is_issuer' => false,
            'body' => 'هل يشمل السعر تكاليف التركيب والتدريب؟',
        ]);
    }

    public function replyTo(Comment $parent): self
    {
        return $this->state([
            'competition_id' => $parent->competition_id,
            'parent_id' => $parent->id,
            'body' => 'نعم، يشمل السعر التركيب والتدريب لفريق التشغيل.',
        ]);
    }
}
