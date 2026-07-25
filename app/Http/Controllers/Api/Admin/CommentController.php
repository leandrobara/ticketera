<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\DeleteCommentRequest;
use App\Http\Requests\Admin\ListCommentRequest;
use App\Http\Requests\Admin\UpdateCommentRequest;
use App\Models\Comment;
use App\Services\Api\Admin\CommentService;

class CommentController extends BaseAPIController
{
    public function list(ListCommentRequest $request): array
    {
        return $this->getSuccessResponse(
            resolve(CommentService::class)->list($request->validated())
        );
    }

    public function update(Comment $comment, UpdateCommentRequest $request): array
    {
        return $this->getSuccessResponse(
            resolve(CommentService::class)->update($comment, $request->validated())
        );
    }

    public function delete(Comment $comment, DeleteCommentRequest $request): array
    {
        resolve(CommentService::class)->delete($comment);
        return $this->getSuccessResponse($comment);
    }
}
