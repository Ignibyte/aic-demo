<?php

declare(strict_types=1);

namespace App\Reports\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use App\Reports\Service\ExportWork;
use Override;
use Throwable;

/**
 * The `prune-exports` job (#21), which the `prune-exports` schedule queues each night at 04:15 UTC: removes every
 * export past its seven days by the database's clock, through `ExportWork`. A younger export stays.
 */
final readonly class PruneReportExports implements Job
{
    /**
     * @param ExportWork $exportWork Prunes the exports.
     */
    public function __construct(
        private ExportWork $exportWork,
    ) {
    }

    /**
     * @param JobInput $jobInput Nothing: the payload is empty.
     *
     * @throws Throwable When the exports cannot be read or one deleted; the queue retries.
     *
     * @return JobResponse Done.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $this->exportWork->prune();

        return new JobDone();
    }
}
