<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Form\Application\PostedFields;
use App\Reports\Entity\ReportKind;
use App\Reports\Entity\ReportKinds;
use App\Reports\Form\RangeChecks;
use App\Reports\Form\RangeFilters;
use App\Reports\Repository\ExportRepository;
use App\Staff\Entity\StaffMember;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Asking for a CSV export (#21): a manager posts a report and its range; the export is stored and its job queued in
 * one transaction, and the answer lands on the reports over that range with `export={id}`, which `ExportDesk` tells
 * the state of.
 */
final readonly class ExportPosts
{
    /**
     * @param ExportRepository $exportRepository The exports.
     * @param RangeChecks      $rangeChecks      Checks the posted range.
     * @param ReportAccess     $reportAccess     Managers only, and whose export it is.
     * @param PostedFields     $postedFields     Reads the posted fields.
     * @param ReportKinds      $reportKinds      Reads the posted report.
     */
    public function __construct(
        private ExportRepository $exportRepository,
        private RangeChecks $rangeChecks,
        private ReportAccess $reportAccess,
        private PostedFields $postedFields = new PostedFields(),
        private ReportKinds $reportKinds = new ReportKinds(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted export form, with its session.
     *
     * @throws Throwable When the export cannot be stored or its job queued.
     *
     * @return PageResponse 303 to the reports over the range with the export named; 403 for a member who is not a
     *                      manager; 422 for no report the page has, or a range that is not one.
     */
    public function post(ServerRequestInterface $serverRequest): PageResponse
    {
        if (!$this->reportAccess->manager($serverRequest) instanceof StaffMember) {
            return $this->reportAccess->refused();
        }

        $reportKind = $this->reportKinds->named($this->postedFields->value($serverRequest, 'report'));
        $from = $this->postedFields->value($serverRequest, 'from');
        $rangeCheck = $this->rangeChecks->check($from, $this->postedFields->value($serverRequest, 'to'));
        $userId = $this->reportAccess->userId($serverRequest);

        if (!$reportKind instanceof ReportKind || $rangeCheck->refused() || $userId === 0) {
            return $this->reportAccess->unexportable();
        }

        $reportRange = $rangeCheck->reportRange;
        $id = $this->exportRepository->queue($userId, $reportKind, $reportRange);

        return new PageRedirect(RangeFilters::PAGE . '?' . $reportRange->query() . '&export=' . $id);
    }
}
