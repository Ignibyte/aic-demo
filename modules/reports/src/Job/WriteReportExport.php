<?php

declare(strict_types=1);

namespace App\Reports\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use App\Reports\Service\ExportWork;
use Override;
use Throwable;

use function ctype_digit;
use function strlen;

/**
 * The `report-export` job (#21), which an export's post queues with the export's id: writes the export's CSV and tells
 * its member, through `ExportWork`.
 */
final readonly class WriteReportExport implements Job
{
    /**
     * @param ExportWork $exportWork Writes the export.
     */
    public function __construct(
        private ExportWork $exportWork,
    ) {
    }

    /**
     * @param JobInput $jobInput The payload: `export`, the export's id.
     *
     * @throws Throwable When the export or its figures cannot be read, its file written or its member told; the queue
     *                   retries.
     *
     * @return JobResponse Done; failed for a payload that names no export.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $id = $jobInput->value('export', '');

        if ($id === '' || strlen($id) > 18 || !ctype_digit($id)) {
            return new JobFailed('The report-export payload names no export: "' . $id . '".');
        }

        $this->exportWork->write((int) $id);

        return new JobDone();
    }
}
