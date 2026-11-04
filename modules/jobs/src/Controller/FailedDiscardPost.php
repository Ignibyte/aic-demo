<?php

declare(strict_types=1);

namespace App\Jobs\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Jobs\Service\FailedMoves;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /app/jobs/failed/{id}/discard (#35): an admin's Discard drops a failed job from AIC's failed record through
 * `PostgresQueue::discard()`; 303 to the jobs page, 404 and 403.
 */
final readonly class FailedDiscardPost implements Page
{
    /**
     * @param FailedMoves $failedMoves Retries and discards failed jobs.
     */
    public function __construct(
        private FailedMoves $failedMoves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted Discard form, with its session.
     *
     * @throws Throwable When the failed record cannot be changed, or a page cannot be built.
     *
     * @return PageResponse 303 to the jobs page; 404 when no failed job has the id; 403 for anyone but an admin.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->failedMoves->discard($serverRequest);
    }
}
