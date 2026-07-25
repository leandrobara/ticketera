<?php

namespace App\Services\Api;

use App\Models\CommentToken;
use RuntimeException;

class CommentInvitationEmailService
{
    public function __construct(
        private readonly BrevoTransactionalEmailService $brevoTransactionalEmailService,
    ) {
        //
    }

    public function send(CommentToken $commentToken, string $rawToken): string
    {
        if (blank(config('brevo.sender.email'))) {
            throw new RuntimeException('brevo_sender_email_not_configured');
        }

        $commentToken->loadMissing(['buyer', 'show']);
        $response = $this->brevoTransactionalEmailService->send([
            'sender' => [
                'name' => config('brevo.sender.name'),
                'email' => config('brevo.sender.email'),
            ],
            'to' => [
                [
                    'name' => trim($commentToken->buyer->name.' '.$commentToken->buyer->last_name),
                    'email' => $commentToken->buyer->email,
                ],
            ],
            'subject' => 'Contanos qué te pareció '.$commentToken->show->title,
            'htmlContent' => view('emails.comment-invitation', [
                'commentToken' => $commentToken,
                'commentUrl' => url('/comentarios/'.$rawToken),
            ])->render(),
        ]);

        return $response['messageId'] ?? '';
    }
}
