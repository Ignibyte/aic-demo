<?php

declare(strict_types=1);

namespace App\Jobs\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Paragraph;
use AIC\Queue\Domain\FailedJob;
use App\Theme\Components\DetailCard\Fact;
use App\Theme\Components\JobCard\JobCard;
use App\Theme\Components\StatusBadge\BadgeTone;
use App\Theme\Components\StatusBadge\StatusBadge;
use InvalidArgumentException;

use function count;

/**
 * The failed jobs as cards (#35), one `JobCard` per `FailedJob` in the order `QueueRecords::failed()` gives them,
 * newest first: its name, a Failed badge, what it was about, its attempts, when it failed and the reason, with Retry
 * now and Discard posting to `/app/jobs/failed/{id}/retry` and `…/discard`. Each card is keyed by the failed job's id.
 */
final readonly class FailedCards
{
    /**
     * Where a failed job's routes start.
     */
    public const string PATHS = '/app/jobs/failed/';

    /**
     * @param JobWords $jobWords The page's words.
     */
    public function __construct(
        private JobWords $jobWords = new JobWords(),
    ) {
    }

    /**
     * @param list<FailedJob> $failedJobs  The failed jobs, newest first.
     * @param GuardFields     $guardFields The visitor's guard fields, for each card's two forms.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return list<KeyedComponent> The summary, then a card per failed job; "No job has failed." when there are none.
     */
    public function cards(array $failedJobs, GuardFields $guardFields): array
    {
        if ($failedJobs === []) {
            return [new KeyedComponent('none', new Paragraph('No job has failed.', 'jobs-failed-none'))];
        }

        $cards = [new KeyedComponent('summary', new Paragraph($this->jobWords->failed(count($failedJobs)), 'jobs-failed-summary'))];

        foreach ($failedJobs as $failedJob) {
            $cards[] = new KeyedComponent('failed-' . $failedJob->id, $this->card($failedJob, $guardFields));
        }

        return $cards;
    }

    /**
     * @param FailedJob   $failedJob   A failed job.
     * @param GuardFields $guardFields The visitor's guard fields.
     *
     * @return Component Its card.
     */
    private function card(FailedJob $failedJob, GuardFields $guardFields): Component
    {
        $path = self::PATHS . $failedJob->id;

        return new JobCard(
            'failed-' . $failedJob->id,
            $failedJob->job,
            new StatusBadge('Failed', BadgeTone::Error),
            [
                new Fact('About', $this->jobWords->about($failedJob->job, $failedJob->payload), false),
                new Fact('Attempts', (string) $failedJob->attempts, false),
                new Fact('Failed at', $failedJob->failedAt, true),
                new Fact('Number', (string) $failedJob->id, true),
            ],
            $failedJob->reason,
            $path . '/retry',
            $path . '/discard',
            $guardFields,
        );
    }
}
