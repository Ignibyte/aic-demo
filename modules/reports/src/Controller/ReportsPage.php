<?php

declare(strict_types=1);

namespace App\Reports\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Reports\Service\ReportAccess;
use App\Reports\Service\ReportPanels;
use App\Staff\Entity\StaffMember;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /app/reports (#21): the managers' four reports over a range, this month unless `from` and `to` name another; a
 * refused range answers 422 with each error beside its field and this month's reports. A salesperson gets 403. The
 * prefix is protected, so only signed-in staff reach it.
 */
final readonly class ReportsPage implements Page
{
    /**
     * @param ReportAccess $reportAccess Managers only.
     * @param ReportPanels $reportPanels The reports' document.
     */
    public function __construct(
        private ReportAccess $reportAccess,
        private ReportPanels $reportPanels,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws RuntimeException When the figures cannot be read, or the kept role is no role.
     * @throws LogicException   When the request has no session, or a key or a region does not fit the theme.
     *
     * @return PageResponse The reports; 422 for a refused range; 403 for a salesperson.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->reportAccess->manager($serverRequest) instanceof StaffMember
            ? $this->reportPanels->page($serverRequest)
            : $this->reportAccess->refused();
    }
}
