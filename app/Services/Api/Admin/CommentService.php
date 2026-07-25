<?php

namespace App\Services\Api\Admin;

use App\Models\Comment;
use App\Repositories\CommentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommentService
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->commentRepository->listAdmin(
            $filters,
            (int) ($filters['per_page'] ?? 20),
        );
    }

    public function update(Comment $comment, array $data): Comment
    {
        return $this->commentRepository->update($comment, $data);
    }

    public function delete(Comment $comment): void
    {
        $this->commentRepository->delete($comment);
    }
}
