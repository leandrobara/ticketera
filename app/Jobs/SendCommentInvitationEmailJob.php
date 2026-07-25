<?php

namespace App\Jobs;

use App\Models\CommentToken;
use App\Services\Api\CommentInvitationEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCommentInvitationEmailJob implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $commentTokenId,
        private readonly string $rawToken,
    ) {
        //
    }

    public function handle(CommentInvitationEmailService $commentInvitationEmailService): void
    {
        $commentToken = CommentToken::query()
            ->with(['buyer', 'show'])
            ->find($this->commentTokenId);

        if (!$commentToken || $commentToken->used_at || $commentToken->revoked_at || $commentToken->expires_at->isPast()) {
            return;
        }

        $commentInvitationEmailService->send($commentToken, $this->rawToken);
    }
}
