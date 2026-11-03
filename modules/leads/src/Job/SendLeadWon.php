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
use App\Leads\Entity\LeadStage;
use App\Leads\Repository\LeadRepository;
use App\Leads\Service\LeadNotices;
use InvalidArgumentException;
use JsonException;
use Override;

use function ctype_digit;
use function strlen;

/**
 * The `lead-won` job (#30), queued in the same transaction as a lead's move to Won on the board: tells the lead's
 * salesperson in their inbox, through AIC's notifications, whose delivery the same worker runs next. A lead that is
 * gone, one moved off Won before the job runs, and one with no salesperson are done with nothing sent.
 */
final readonly class SendLeadWon implements Job
{
    /**
     * @param LeadNotices    $leadNotices    Sends its notification.
     * @param LeadRepository $leadRepository Reads the lead.
     */
    public function __construct(
        private LeadNotices $leadNotices,
        private LeadRepository $leadRepository,
    ) {
    }

    /**
     * @param JobInput $jobInput The payload: `lead`, the lead's id, and `by`, the name of who moved it.
     *
     * @throws QueryException           When the lead cannot be read or the delivery cannot be queued; the queue retries.
     * @throws InvalidArgumentException When a type is malformed.
     * @throws JsonException            When the delivery's payload cannot be encoded.
     *
     * @return JobResponse Done, whether or not a notification went; failed for a payload that names no lead.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $named = $jobInput->value('lead', '');
        $digits = strlen($named) <= 18 && ctype_digit($named);
        $lead = $digits
            ? $this->leadRepository->find((int) $named)
            : null;

        if ($lead instanceof Lead && $lead->stage === LeadStage::Won) {
            $this->leadNotices->won($lead, $jobInput->value('by', 'Someone'));
        }

        return $digits
            ? new JobDone()
            : new JobFailed('No lead is named by this lead-won payload: "' . $named . '".');
    }
}
