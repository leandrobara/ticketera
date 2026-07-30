<?php

namespace App\Http\Controllers\Api\Site;

use App\Models\Show;
use App\Services\Api\Site\CommentService;
use App\Http\Requests\Site\ListCommentRequest;
use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Site\CreateCommentRequest;
use App\Http\Resources\Site\CommentResourceCollection;
use App\Http\Requests\Site\RequestCommentTokenRequest;
use App\Http\Requests\Site\ValidateCommentTokenRequest;


class CommentController extends BaseAPIController
{
    public function __construct(
        private readonly CommentService $commentService,
    ) {
        //
    }

    public function listByShow(Show $show, ListCommentRequest $request): array
    {
        $commentData = $this->commentService->listByShow($show, $request->validated());
        $commentRs = new CommentResourceCollection(
            $commentData['comments'],
            $commentData['comments_summary'],
        );

        return $this->getSuccessResponse($commentRs);
    }

    public function create(string $token, CreateCommentRequest $request): array
    {
        return $this->getSuccessResponse(
            $this->commentService->create($token, $request->validated())
        );
    }

    public function sendUserEmailToComment(Show $show, RequestCommentTokenRequest $request): array
    {
        return $this->getSuccessResponse(
            $this->commentService->sendUserEmailToComment($show, $request->validated())
        );
    }

    public function validateToken(string $token, ValidateCommentTokenRequest $request): array
    {
        return $this->getSuccessResponse(
            $this->commentService->validateToken($token)
        );
    }
}
