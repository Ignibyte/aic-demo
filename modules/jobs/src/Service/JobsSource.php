<?php

declare(strict_types=1);

namespace App\Jobs\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Application\QueueRecords;
use App\Theme\Components\LiveParts\LiveParts;
use App\Theme\Components\Panel\Panel;
use InvalidArgumentException;
use Override;

/**
 * The jobs page's live part as a source (#35, AIC #192): the panel "In the queue" and the panel "Failed" in the
 * theme's `LiveParts`, which Tessera refreshes from `/app/jobs` every five seconds. It lists the queue for the
 * panels only in `build()`, which the render pass calls when it renders the part, so a refresh answered 204 never
 * builds them; the version that answer rests on reads the queue on its own, in `JobsLive::version()`. Built per
 * request with the visitor's guard fields, which each failed job's two forms carry.
 */
final readonly class JobsSource implements ComponentSource
{
    /**
     * @param QueueRecords $queueRecords AIC's queue as typed records.
     * @param GuardFields  $guardFields  The visitor's guard fields.
     * @param QueueTable   $queueTable   The queued jobs' table.
     * @param FailedCards  $failedCards  The failed jobs' cards.
     */
    public function __construct(
        private QueueRecords $queueRecords,
        private GuardFields $guardFields,
        private QueueTable $queueTable = new QueueTable(),
        private FailedCards $failedCards = new FailedCards(),
    ) {
    }

    /**
     * @throws QueryException           When the queue cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Component The two panels, keyed `queue` and `failed`, in a `LiveParts` polling the page.
     */
    #[Override]
    public function build(): Component
    {
        $queue = new Panel('jobs-queue', 'In the queue', 'What waits, runs or retries.', true, [
            new KeyedComponent('table', $this->queueTable->table($this->queueRecords->queued())),
        ]);
        $failed = new Panel('jobs-failed', 'Failed', 'What failed on its last attempt, with the reason.', true, $this->failedCards->cards(
            $this->queueRecords->failed(),
            $this->guardFields,
        ));

        return new LiveParts(JobsAccess::PAGE, [new KeyedComponent('queue', $queue), new KeyedComponent('failed', $failed)]);
    }
}
