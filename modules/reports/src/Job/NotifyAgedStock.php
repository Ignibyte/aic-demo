<?php

declare(strict_types=1);

namespace App\Reports\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Database\Contracts\QueryException;
use App\Reports\Entity\ReportConfig;
use App\Reports\Repository\ReportRepository;
use App\Reports\Service\ReportNotices;
use App\Staff\Entity\Role;
use InvalidArgumentException;
use JsonException;
use Override;

/**
 * The `aged-stock` job (#20), which the `aged-stock` schedule queues each morning at 07:00 UTC: lists the vehicles not
 * sold on the lot longer than config/reports.php's threshold in one notification to each sales manager. With none, it
 * sends nothing.
 */
final readonly class NotifyAgedStock implements Job
{
    /**
     * @param ReportRepository $reportRepository The aged vehicles and the sales managers.
     * @param ReportNotices    $reportNotices    Sends the notification.
     * @param ReportConfig     $reportConfig     The threshold.
     */
    public function __construct(
        private ReportRepository $reportRepository,
        private ReportNotices $reportNotices,
        private ReportConfig $reportConfig,
    ) {
    }

    /**
     * @param JobInput $jobInput Nothing: the payload is empty.
     *
     * @throws QueryException           When the vehicles or the staff cannot be read, or a delivery cannot be queued; the
     *                                  queue retries.
     * @throws InvalidArgumentException When an entity type is malformed.
     * @throws JsonException            When a delivery's payload cannot be encoded.
     *
     * @return JobResponse Done.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $days = $this->reportConfig->agedStockDays;
        $vehicles = $this->reportRepository->agedStock($days);

        if ($vehicles !== []) {
            $this->reportNotices->agedStock($vehicles, $days, $this->reportRepository->members([Role::SalesManager]));
        }

        return new JobDone();
    }
}
