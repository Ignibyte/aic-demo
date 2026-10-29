<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\FileDownload;
use AIC\Routing\Domain\RouteNotFoundException;
use App\Reports\Entity\ReportExport;
use App\Reports\Repository\ExportRepository;
use App\Staff\Service\StaffRequests;
use App\Theme\Components\DownloadLink\DownloadLink;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function ctype_digit;
use function strlen;

/**
 * A CSV export once it is asked for (#21): the reports page's line about the export its query names, preparing or
 * ready to download; and the download, which only its member gets, ready and within its seven days. Every other case
 * of the download answers 404, so another member cannot tell that an export is there.
 */
final readonly class ExportDesk
{
    /**
     * The download's media type.
     */
    private const string CSV = 'text/csv; charset=utf-8';

    /**
     * The line's id.
     */
    private const string STATUS = 'report-export-status';

    /**
     * @param ExportRepository $exportRepository The exports.
     * @param ReportAccess     $reportAccess     Whose exports they are.
     * @param ExportFiles      $exportFiles      The exports' files.
     * @param StaffRequests    $staffRequests    Reads the query and the route's id.
     */
    public function __construct(
        private ExportRepository $exportRepository,
        private ReportAccess $reportAccess,
        private ExportFiles $exportFiles,
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The reports page's request: `export` in its query names one.
     *
     * @throws RuntimeException When the export cannot be read.
     * @throws LogicException   When the request carries no session, or an entity type or a key is malformed.
     *
     * @return list<KeyedComponent> The export's line, preparing or ready with its download link; none when the query
     *                              names no export of the member's within its seven days.
     */
    public function status(ServerRequestInterface $serverRequest): array
    {
        $reportExport = $this->own($this->staffRequests->query($serverRequest, 'export'), $serverRequest);

        if (!$reportExport instanceof ReportExport) {
            return [];
        }

        $what = 'The ' . $reportExport->reportKind->title() . ' CSV for ' . $reportExport->reportRange->from . ' to '
            . $reportExport->reportRange->until;

        if (!$reportExport->ready) {
            $message = $what . ' is being prepared. You will be notified when it is ready.';

            return [new KeyedComponent('export', new Notice($message, NoticeTone::Info, self::STATUS))];
        }

        return [
            new KeyedComponent('export', new Notice($what . ' is ready.', NoticeTone::Success, self::STATUS)),
            new KeyedComponent('download', new DownloadLink('report-export-download', 'Download the CSV', $reportExport->path())),
        ];
    }

    /**
     * @param ServerRequestInterface $serverRequest The download's request: the route's id, its range and conditions.
     *
     * @throws LogicException   When the request carries no session, or an entity type is malformed.
     * @throws RuntimeException When the export cannot be read, or it is not the member's ready export within its seven
     *                          days, or its file is gone (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return FileDownload The CSV, saved under its report's and range's name; AIC answers its ETag, conditions and
     *                      byte range.
     */
    public function download(ServerRequestInterface $serverRequest): FileDownload
    {
        $reportExport = $this->own($this->staffRequests->attribute($serverRequest, 'id'), $serverRequest);

        if (
            !$reportExport instanceof ReportExport
            || !$reportExport->ready
            || !$this->exportFiles->exists($reportExport->id)
        ) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        return new FileDownload($this->exportFiles->path($reportExport->id), self::CSV, $reportExport->fileName(), $serverRequest);
    }

    /**
     * @param string                 $id            An export's id as a request gave it.
     * @param ServerRequestInterface $serverRequest The request, with the session of who asks for it.
     *
     * @throws RuntimeException When the export cannot be read.
     * @throws LogicException   When the request carries no session, or an entity type is malformed.
     *
     * @return ReportExport|null The export when the id is one and it is the signed-in member's within its seven days;
     *                           else null.
     */
    private function own(string $id, ServerRequestInterface $serverRequest): ?ReportExport
    {
        $userId = $this->reportAccess->userId($serverRequest);

        return $id === '' || strlen($id) > 18 || !ctype_digit($id) || $userId === 0
            ? null
            : $this->exportRepository->own((int) $id, $userId);
    }
}
