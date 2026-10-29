<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Notifier;
use AIC\Notification\Contracts\Notification;
use App\Reports\Entity\ReportExport;
use JsonException;

use function str_contains;

/**
 * Tells the member who asked for an export that its file is ready (#21), through AIC's Notifier, one `notify` job
 * per channel: kept in the database, so the inbox and the bell link the download, and mailed when their login is an
 * address (the gate's own user, `gate`, is not).
 */
final readonly class ExportNotices
{
    /**
     * @param Notifier $notifier Queues each delivery.
     */
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    /**
     * @param ReportExport $reportExport An export whose file is written.
     * @param string       $email        The login of the member who asked for it.
     *
     * @throws JsonException  When a delivery's payload cannot be encoded.
     * @throws QueryException When a delivery cannot be queued.
     */
    public function ready(ReportExport $reportExport, string $email): void
    {
        $title = $reportExport->reportKind->title();
        $range = $reportExport->reportRange->from . ' to ' . $reportExport->reportRange->until;
        $body = 'The ' . $title . ' report for ' . $range . ' is ready as CSV. It downloads for seven days.'
            . "\n\nDownload it: " . $reportExport->path();
        $notification = new Notification(
            (string) $reportExport->userId,
            $email,
            'Your ' . $title . ' CSV for ' . $range . ' is ready',
            $body,
            $reportExport->path(),
        );
        $channels = str_contains($email, '@')
            ? ['database', 'mail']
            : ['database'];
        $this->notifier->send($notification, $channels);
    }
}
