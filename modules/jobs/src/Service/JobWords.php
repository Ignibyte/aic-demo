<?php

declare(strict_types=1);

namespace App\Jobs\Service;

use AIC\Queue\Domain\QueueState;
use AIC\Session\Domain\StringMapJson;
use App\Theme\Components\StatusBadge\BadgeTone;

use function implode;
use function number_format;

/**
 * The words the jobs page shows (#35). A job's About is read from its payload with AIC's `StringMapJson`, the decoder
 * the queue itself uses: AIC's `notify` job names its channel, its address or user, and its subject; any other job
 * is described by its payload's keys and values alone, so the page knows no other module's jobs. A state's label and
 * badge tone come from its `QueueState`, and each list's summary counts what it shows.
 */
final readonly class JobWords
{
    /**
     * AIC's notification delivery (`Notifier::JOB`).
     */
    private const string NOTIFY = 'notify';

    /**
     * @param StringMapJson $stringMapJson Reads a payload.
     */
    public function __construct(
        private StringMapJson $stringMapJson = new StringMapJson(),
    ) {
    }

    /**
     * @param string $job     The job's name.
     * @param string $payload Its payload, as the queue keeps it.
     *
     * @return string What the job is about: "Mail to {email}: {subject}" or "Inbox of user {user}: {subject}" for a
     *                notification, its payload's keys and values in order for any other job, "Nothing" for none.
     */
    public function about(string $job, string $payload): string
    {
        $values = $this->stringMapJson->decode($payload);
        $notification = $job === self::NOTIFY
            ? self::notification($values)
            : '';

        return $notification !== ''
            ? $notification
            : self::pairs($values);
    }

    /**
     * @param QueueState $queueState A queued job's state.
     *
     * @return string Its label.
     */
    public function state(QueueState $queueState): string
    {
        return match ($queueState) {
            QueueState::Waiting => 'Waiting',
            QueueState::Running => 'Running',
            QueueState::Retrying => 'Retrying',
        };
    }

    /**
     * @param QueueState $queueState A queued job's state.
     *
     * @return BadgeTone Its badge's colour.
     */
    public function tone(QueueState $queueState): BadgeTone
    {
        return match ($queueState) {
            QueueState::Waiting => BadgeTone::Neutral,
            QueueState::Running => BadgeTone::Success,
            QueueState::Retrying => BadgeTone::Warning,
        };
    }

    /**
     * @param string $reservedAt When a worker took the job, ISO 8601 in UTC; empty when none has.
     *
     * @return string The time, or "Not started".
     */
    public function started(string $reservedAt): string
    {
        return $reservedAt === ''
            ? 'Not started'
            : $reservedAt;
    }

    /**
     * @param int $count How many jobs the queue's table shows.
     *
     * @return string Its summary.
     */
    public function queued(int $count): string
    {
        return number_format($count) . ($count === 1 ? ' job' : ' jobs') . ' in the queue, newest first';
    }

    /**
     * @param int $count How many failed jobs the page shows.
     *
     * @return string Their summary.
     */
    public function failed(int $count): string
    {
        return number_format($count) . ($count === 1 ? ' failed job' : ' failed jobs') . ', newest first';
    }

    /**
     * @param array<string, string> $values A notification's payload.
     *
     * @return string "Mail to {email}: {subject}" on the `mail` channel, "Inbox of user {user}: {subject}" on
     *                `database`; empty on any other.
     */
    private static function notification(array $values): string
    {
        $subject = $values['subject'] ?? '';

        return match ($values['channel'] ?? '') {
            'mail' => 'Mail to ' . ($values['email'] ?? '') . ': ' . $subject,
            'database' => 'Inbox of user ' . ($values['user'] ?? '') . ': ' . $subject,
            default => '',
        };
    }

    /**
     * @param array<string, string> $values A payload.
     *
     * @return string Its keys and values in order ("chat 12"), or "Nothing" for none.
     */
    private static function pairs(array $values): string
    {
        $pairs = [];

        foreach ($values as $key => $value) {
            $pairs[] = $key . ' ' . $value;
        }

        return $pairs === []
            ? 'Nothing'
            : implode(', ', $pairs);
    }
}
