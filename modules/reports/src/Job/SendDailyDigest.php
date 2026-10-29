<?php

declare(strict_types=1);

namespace App\Reports\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Database\Contracts\QueryException;
use App\Reports\Repository\ReportRepository;
use App\Reports\Service\ReportNotices;
use App\Staff\Entity\Role;
use InvalidArgumentException;
use JsonException;
use Override;

/**
 * The `daily-digest` job (#20), which the `daily-digest` schedule queues each night at 21:00 UTC: mails each manager
 * the day's sales, leads and new stock. It sends no mail itself: each mail is a `notify` job, which retries after a
 * delay while the mail server cannot be reached and fails on its fifth attempt with the reason (AIC #208).
 */
final readonly class SendDailyDigest implements Job
{
    /**
     * @param ReportRepository $reportRepository The day's figures and the managers.
     * @param ReportNotices    $reportNotices    Sends the digest.
     */
    public function __construct(
        private ReportRepository $reportRepository,
        private ReportNotices $reportNotices,
    ) {
    }

    /**
     * @param JobInput $jobInput Nothing: the payload is empty.
     *
     * @throws QueryException           When the figures or the staff cannot be read, or a delivery cannot be queued; the
     *                                  queue retries.
     * @throws InvalidArgumentException When an entity type is malformed.
     * @throws JsonException            When a delivery's payload cannot be encoded.
     *
     * @return JobResponse Done.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $this->reportNotices->digest($this->reportRepository->day(), $this->reportRepository->members([Role::Admin, Role::SalesManager]));

        return new JobDone();
    }
}
