<?php

declare(strict_types=1);

namespace App\Jobs\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\SourcedComponent;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Application\FormTokens;
use AIC\Queue\Application\QueueRecords;
use JsonException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function hash;
use function implode;

/**
 * The jobs page's live part (#35), in the dashboard's shape (#14, AIC #193): the part sits under its key and anchor,
 * its version sealed in its props, which `JobsPoll` compares a refresh's props with. The version is a SHA-256 over
 * what the part shows: each queued job's id, state, attempts and times, and each failed job's id and time.
 */
final readonly class JobsLive
{
    /**
     * The part's key in the panel grid, after the intro.
     */
    public const string KEY = 'live';

    /**
     * The part's anchor, `data-t-anchor="jobs-live"` on its root.
     */
    public const string ANCHOR = 'jobs-live';

    /**
     * @param QueueRecords  $queueRecords  AIC's queue as typed records.
     * @param EnvelopeCodec $envelopeCodec Seals the part's props.
     * @param FormTokens    $formTokens    Issues the visitor's guard fields.
     */
    public function __construct(
        private QueueRecords $queueRecords,
        private EnvelopeCodec $envelopeCodec,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @throws QueryException When the queue cannot be read.
     *
     * @return string What the part shows now, as a SHA-256 in hex.
     */
    public function version(): string
    {
        $lines = [];

        foreach ($this->queueRecords->queued() as $queuedJob) {
            $lines[] = 'q ' . $queuedJob->id . ' ' . $queuedJob->state->value . ' ' . $queuedJob->attempts
                . ' ' . $queuedJob->dueAt . ' ' . $queuedJob->reservedAt;
        }

        foreach ($this->queueRecords->failed() as $failedJob) {
            $lines[] = 'f ' . $failedJob->id . ' ' . $failedJob->failedAt;
        }

        return hash('sha256', implode("\n", $lines));
    }

    /**
     * @param ServerRequestInterface $serverRequest The jobs page's request, with its session.
     * @param string                 $version       What the part shows now.
     *
     * @throws JsonException  When the props cannot be sealed.
     * @throws LogicException When the request carries no session, or the key, the anchor or the token breaks the
     *                        grammar.
     *
     * @return KeyedComponent The part under its key and anchor, its version sealed in its props, built from the queue
     *                        when it is rendered.
     */
    public function part(ServerRequestInterface $serverRequest, string $version): KeyedComponent
    {
        $props = $this->envelopeCodec->seal(['version' => $version]);
        $jobsSource = new JobsSource($this->queueRecords, $this->formTokens->guard($serverRequest));

        return new KeyedComponent(self::KEY, new SourcedComponent($jobsSource), $props, self::ANCHOR);
    }
}
