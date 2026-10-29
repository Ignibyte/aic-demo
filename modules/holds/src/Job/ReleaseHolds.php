<?php

declare(strict_types=1);

namespace App\Holds\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use App\Holds\Repository\HoldReleaseRepository;
use App\Holds\Repository\HoldRepository;
use App\Holds\Service\HoldNotices;
use Override;
use Throwable;

/**
 * The `release-holds` job (#20), which the `release-holds` schedule queues every 15 minutes: releases each active hold
 * whose 48 hours have passed, each in its own transaction with its notification queued inside it. A hold that ended
 * meanwhile is skipped, so a second run releases nothing twice.
 */
final readonly class ReleaseHolds implements Job
{
    /**
     * @param HoldRepository        $holdRepository        The expired holds.
     * @param HoldReleaseRepository $holdReleaseRepository Releases each.
     * @param HoldNotices           $holdNotices           Tells who placed each one.
     */
    public function __construct(
        private HoldRepository $holdRepository,
        private HoldReleaseRepository $holdReleaseRepository,
        private HoldNotices $holdNotices,
    ) {
    }

    /**
     * @param JobInput $jobInput Nothing: the payload is empty.
     *
     * @throws Throwable When the holds cannot be read or a release fails; the queue retries.
     *
     * @return JobResponse Done.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        foreach ($this->holdRepository->expired() as $hold) {
            $this->holdReleaseRepository->release($hold, $this->holdNotices);
        }

        return new JobDone();
    }
}
