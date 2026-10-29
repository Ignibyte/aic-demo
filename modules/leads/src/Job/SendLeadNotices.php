<?php

declare(strict_types=1);

namespace App\Leads\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use AIC\Database\Contracts\QueryException;
use App\Leads\Entity\Lead;
use App\Leads\Repository\LeadRepository;
use App\Leads\Service\LeadNotices;
use InvalidArgumentException;
use JsonException;
use Override;

use function ctype_digit;
use function strlen;

/**
 * The `lead-notices` job (#18), queued when a visitor's lead is recorded: tells the lead's salesperson and the visitor
 * through AIC's notifications, whose deliveries the same worker runs next. A lead that is gone (its car was deleted,
 * and its leads with it) is done with nothing sent.
 */
final readonly class SendLeadNotices implements Job
{
    /**
     * @param LeadRepository $leadRepository Reads the lead.
     * @param LeadNotices    $leadNotices    Sends its notifications.
     */
    public function __construct(
        private LeadRepository $leadRepository,
        private LeadNotices $leadNotices,
    ) {
    }

    /**
     * @param JobInput $jobInput The payload: `lead`, the lead's id.
     *
     * @throws QueryException           When the lead cannot be read or a delivery cannot be queued; the queue retries.
     * @throws InvalidArgumentException When a type is malformed.
     * @throws JsonException            When a delivery's payload cannot be encoded.
     *
     * @return JobResponse Done; failed for a payload that names no lead.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $id = $jobInput->value('lead', '');

        if ($id === '' || strlen($id) > 18 || !ctype_digit($id)) {
            return new JobFailed('The lead-notices payload names no lead: "' . $id . '".');
        }

        $lead = $this->leadRepository->find((int) $id);

        if ($lead instanceof Lead) {
            $this->leadNotices->send($lead);
        }

        return new JobDone();
    }
}
