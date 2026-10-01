<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Resources;

use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Models\Comment;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Comment (API.md §2.7), projected for the audience (participants never see other participants'
 * names):
 *
 *   author = issuer       {"kind": "issuer", "organization_name"}                     for everyone
 *   author = participant  issuer:     {"kind": "participant", "alias_no", "organization_name"}
 *                         own org:    {"kind": "me"}
 *                         others:     {"kind": "participant", "alias_no"}
 *
 * `$audienceOrganizationId` is the viewing organization; `$issuerView` is true for the issuer.
 * Replies (loaded as `replies`, oldest first) are nested one level.
 *
 * @mixin Comment
 */
final class CommentResource extends JsonResource
{
    public function __construct(Comment $comment, private readonly int $audienceOrganizationId, private readonly bool $issuerView)
    {
        parent::__construct($comment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'parent_id' => $this->parent_id !== null ? $this->parent?->public_id : null,
            'body' => $this->body,
            'author' => $this->author(),
            'created_at' => Iso::format($this->created_at),
            'replies' => $this->relationLoaded('replies')
                ? $this->replies->map(fn (Comment $reply): array => (new self(
                    $reply->setRelation('parent', $this->resource),
                    $this->audienceOrganizationId,
                    $this->issuerView,
                ))->resolve($request))->values()->all()
                : [],
        ];
    }

    /**
     * The author as this audience may see it, from Bidding's VisibilityProjector (ARCHITECTURE
     * §7.9, §10: the single gate for participant identities).
     *
     * @return array<string, mixed>
     */
    private function author(): array
    {
        return app(VisibilityProjector::class)->commentAuthor($this->resource, $this->issuerView, $this->audienceOrganizationId);
    }
}
