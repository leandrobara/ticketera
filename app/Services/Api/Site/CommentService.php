<?php

namespace App\Services\Api\Site;

use App\Jobs\SendCommentInvitationEmailJob;
use App\Models\Buyer;
use App\Models\Comment;
use App\Models\CommentToken;
use App\Models\Show;
use App\Repositories\BuyerRepository;
use App\Repositories\CommentRepository;
use App\Repositories\CommentTokenRepository;
use App\Repositories\OrderRepository;
use App\Repositories\Site\CommentRepository as SiteCommentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommentService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly BuyerRepository $buyerRepository,
        private readonly CommentRepository $commentRepository,
        private readonly SiteCommentRepository $siteCommentRepository,
        private readonly CommentTokenRepository $commentTokenRepository,
    ) {
        //
    }

    public function listByShow(Show $show, array $options = []): array
    {
        abort_unless($show->comments_enabled, 403, 'comments_disabled');

        return [
            'comments' => $this->siteCommentRepository->listByShow($show, $options),
            'comments_summary' => $this->siteCommentRepository
                ->getCommentsCountAndAverageRatingByShow($show),
        ];
    }

    public function sendUserEmailToComment(Show $show, array $data): array
    {
        abort_unless($show->comments_enabled, 403, 'comments_disabled');
        $genericResponse = [
            'message' => 'Si el email coincide con el email con que realizaste la compra, te enviaremos un enlace para que puedas comentar.',
        ];

        $email = mb_strtolower(trim($data['email']));
        $buyer = Buyer::where('organization_id', $show->organization_id)->where('email', $email)->first();

        if (! $buyer) {
            return $genericResponse;
        }

        $tokenData = DB::transaction(function () use ($show, $buyer) {
            $lockedBuyer = $this->buyerRepository->findByIdForUpdate($buyer->id);

            if (! $lockedBuyer) {
                return null;
            }

            $order = $this->orderRepository->findApprovedForComment($show->id, $lockedBuyer->id);

            if (! $order || $this->hasReachedCommentLimit($lockedBuyer->id, $show->id)) {
                return null;
            }

            $this->commentTokenRepository->revokeActive($lockedBuyer->id, $show->id);

            $rawToken = Str::random(64);
            $commentToken = $this->commentTokenRepository->store([
                'buyer_id' => $lockedBuyer->id,
                'show_id' => $show->id,
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => now()->addHours((int) config('comments.token_expiration_hours')),
            ]);

            return [$commentToken, $rawToken];
        });

        if ($tokenData) {
            [$commentToken, $rawToken] = $tokenData;
            SendCommentInvitationEmailJob::dispatch($commentToken->id, $rawToken)->afterCommit();
        }

        return $genericResponse;
    }

    public function validateToken(string $rawToken): array
    {
        $commentToken = $this->commentTokenRepository->findByHash(hash('sha256', $rawToken));
        $this->assertTokenIsAvailable($commentToken);

        return [
            'show' => [
                'id' => $commentToken->show->id,
                'title' => $commentToken->show->title,
                'slug' => $commentToken->show->slug,
            ],
            'expires_at' => $commentToken->expires_at,
        ];
    }

    public function create(string $rawToken, array $data): Comment
    {
        $tokenSnapshot = $this->commentTokenRepository->findByHash(hash('sha256', $rawToken));

        if (! $tokenSnapshot) {
            throw ValidationException::withMessages(['token' => ['invalid_comment_token']]);
        }

        $comment = DB::transaction(function () use ($rawToken, $data, $tokenSnapshot) {
            $buyer = $this->buyerRepository->findByIdForUpdate($tokenSnapshot->buyer_id);
            $commentToken = $this->commentTokenRepository->findByHash(
                hash('sha256', $rawToken),
                true,
            );
            $this->assertTokenIsAvailable($commentToken);

            if (! $buyer) {
                throw ValidationException::withMessages(['token' => ['comment_buyer_not_available']]);
            }

            $order = $this->orderRepository->findApprovedForComment(
                $commentToken->show_id,
                $commentToken->buyer_id,
            );

            if (! $order) {
                throw ValidationException::withMessages(['token' => ['comment_order_not_approved']]);
            }

            $comment = $this->commentRepository->store([
                'order_id' => $order->id,
                'buyer_id' => $commentToken->buyer_id,
                'show_id' => $commentToken->show_id,
                'name' => $data['name'],
                'rating' => $data['rating'],
                'comment' => $data['comment'],
                'status' => 'visible',
            ]);

            $this->commentTokenRepository->markUsed($commentToken);

            return $comment;
        });

        return $comment;
    }

    private function assertTokenIsAvailable(?CommentToken $commentToken): void
    {
        if (! $commentToken) {
            throw ValidationException::withMessages(['token' => ['invalid_comment_token']]);
        }

        abort_unless($commentToken->show?->comments_enabled, 403, 'comments_disabled');

        if ($commentToken->used_at || $commentToken->revoked_at) {
            throw ValidationException::withMessages(['token' => ['comment_token_not_available']]);
        }

        if ($commentToken->expires_at->isPast()) {
            throw ValidationException::withMessages(['token' => ['comment_token_expired']]);
        }

        if (! $this->orderRepository->findApprovedForComment(
            $commentToken->show_id,
            $commentToken->buyer_id,
        )) {
            throw ValidationException::withMessages(['token' => ['comment_order_not_approved']]);
        }

        if ($this->hasReachedCommentLimit(
            $commentToken->buyer_id,
            $commentToken->show_id,
        )) {
            throw ValidationException::withMessages(['token' => ['comment_limit_reached']]);
        }
    }

    private function hasReachedCommentLimit(int $buyerId, int $showId): bool
    {
        return $this->commentRepository->countForBuyerShow($buyerId, $showId)
            >= (int) config('comments.max_per_buyer_show');
    }
}
