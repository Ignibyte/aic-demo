<?php

declare(strict_types=1);

namespace App\Jobs\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Application\PostgresQueue;
use App\Staff\Service\StaffNotices;
use Closure;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function sprintf;

/**
 * A failed job's Retry now and Discard (#35), through AIC's queue alone: `PostgresQueue::retry()` queues it again with
 * its attempts from 0, and `PostgresQueue::discard()` drops it from the failed record. Each is an admin's: anyone else
 * gets the 403 page before the queue is touched. A change keeps its notice for the jobs page and answers 303 there;
 * an id no failed job has, such as one retried or discarded already, answers 404.
 */
final readonly class FailedMoves
{
    /**
     * @param JobsAccess    $jobsAccess    Admins only; the route's id; the 404 page.
     * @param PostgresQueue $postgresQueue AIC's queue.
     * @param StaffNotices  $staffNotices  Keeps a change's notice.
     */
    public function __construct(
        private JobsAccess $jobsAccess,
        private PostgresQueue $postgresQueue,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted Retry now form, with its session.
     *
     * @throws QueryException           When the queue cannot be changed.
     * @throws LogicException           When the request carries no session, or a refusal's page does not fit the theme.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse 303 to the jobs page; 404; 403 for anyone but an admin.
     */
    public function retry(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->move($serverRequest, $this->postgresQueue->retry(...), 'Queued failed job %d again. It waits with no attempts.');
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted Discard form, with its session.
     *
     * @throws QueryException           When the failed record cannot be changed.
     * @throws LogicException           When the request carries no session, or a refusal's page does not fit the theme.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse 303 to the jobs page; 404; 403 for anyone but an admin.
     */
    public function discard(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->move($serverRequest, $this->postgresQueue->discard(...), 'Discarded failed job %d.');
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     * @param Closure(int): bool     $change        The queue's change for a failed job's id: whether it was there.
     * @param string                 $notice        The notice, its `%d` the id.
     *
     * @throws QueryException           When the queue cannot be changed.
     * @throws LogicException           When the request carries no session, or a refusal's page does not fit the theme.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse 303 to the jobs page; 404; 403 for anyone but an admin.
     */
    private function move(ServerRequestInterface $serverRequest, Closure $change, string $notice): PageResponse
    {
        $refused = $this->jobsAccess->guard($serverRequest);

        if ($refused !== null) {
            return $refused;
        }

        $id = $this->jobsAccess->failedId($serverRequest);

        if ($id === 0 || !$change($id)) {
            return $this->jobsAccess->missing();
        }

        $this->staffNotices->keep($serverRequest, sprintf($notice, $id));

        return new PageRedirect(JobsAccess::PAGE);
    }
}
