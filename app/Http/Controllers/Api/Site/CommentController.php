<?php

namespace App\Http\Controllers\Api\Site;

use App\Models\Show;
use App\Models\Season;
use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Site\CreateCommentRequest;
use App\Http\Requests\Site\ListCommentRequest;
use App\Http\Requests\Site\RequestCommentTokenRequest;
use App\Http\Requests\Site\ValidateCommentTokenRequest;
use App\Http\Resources\Site\CommentResourceCollection;
use App\Services\Api\Site\CommentService;

class CommentController extends BaseAPIController
{
    public function requestToken(
        Show $show,
        RequestCommentTokenRequest $request,
    ): array {
        return $this->getSuccessResponse(
            resolve(CommentService::class)->requestToken($show, $request->validated())
        );
    }

    public function validateToken(string $token, ValidateCommentTokenRequest $request): array
    {
        return $this->getSuccessResponse(
            resolve(CommentService::class)->validateToken($token)
        );
    }

    public function create(string $token, CreateCommentRequest $request): array
    {
        return $this->getSuccessResponse(
            resolve(CommentService::class)->create($token, $request->validated())
        );
    }

    public function list(Season $season, ListCommentRequest $request): array
    {
        return $this->getSuccessResponse(
            new CommentResourceCollection(
                resolve(CommentService::class)->listPublic($season, $request->validated())
            )
        );
    }
}
