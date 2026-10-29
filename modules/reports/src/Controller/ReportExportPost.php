<?php

declare(strict_types=1);

namespace App\Reports\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Reports\Service\ExportPosts;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /app/reports/exports (#21): stores an export of the posted report over the posted range and queues its job,
 * then answers 303 to the reports over that range with the export named; 403 for a salesperson or without the form
 * token, 422 for no report the page has or a range that is not one.
 */
final readonly class ReportExportPost implements Page
{
    /**
     * @param ExportPosts $exportPosts The export's post.
     */
    public function __construct(
        private ExportPosts $exportPosts,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws Throwable When the export cannot be stored or its job queued.
     *
     * @return PageResponse 303 to the reports; 403 or 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->exportPosts->post($serverRequest);
    }
}
