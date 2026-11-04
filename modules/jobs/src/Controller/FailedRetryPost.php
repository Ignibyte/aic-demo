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
 * POST /app/jobs/failed/{id}/retry (#35): an admin's Retry now queues a failed job again through
 * `PostgresQueue::retry()`; 303 to the jobs page, 404 and 403.
 */
final readonly class FailedRetryPost implements Page
{
    /**
     * @param FailedMoves $failedMoves Retries and discards failed jobs.
     */
    public function __construct(
        private FailedMoves $failedMoves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted Retry now form, with its session.
     *
     * @throws Throwable When the queue cannot be changed, or a page cannot be built.
     *
     * @return PageResponse 303 to the jobs page; 404 when no failed job has the id; 403 for anyone but an admin.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->failedMoves->retry($serverRequest);
    }
}
