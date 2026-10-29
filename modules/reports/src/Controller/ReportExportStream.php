<?php

declare(strict_types=1);

namespace App\Reports\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use App\Reports\Service\ExportDesk;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /app/reports/exports/{id} (#21): the signed-in member's own export, once its file is written and within its
 * seven days, as `text/csv` saved under its report's and range's name; AIC answers its ETag, conditions and byte
 * range (206, 304, 412, 416). Any other export, or none, answers 404.
 */
final readonly class ReportExportStream implements Stream
{
    /**
     * @param ExportDesk $exportDesk The exports' flows.
     */
    public function __construct(
        private ExportDesk $exportDesk,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff, routed with the id.
     *
     * @throws LogicException   When the request carries no session, or an entity type is malformed.
     * @throws RuntimeException When the export cannot be read, or is not the member's to download
     *                          (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return StreamResponse The CSV.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->exportDesk->download($serverRequest);
    }
}
