<?php

declare(strict_types=1);

namespace App\Reports\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\QueryException;
use App\Reports\Entity\ApiMessage;
use App\Reports\Entity\SalesReportJson;
use App\Reports\Form\RangeChecks;
use App\Reports\Repository\FigureRepository;
use App\Reports\Service\ReportAccess;
use App\Staff\Entity\StaffMember;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /api/reports/sales?from=&to= (#21): the sales report as JSON for a signed-in manager, this month when neither day
 * is given; 422 for a range that is not one, 401 for a visitor who has not signed in and 403 for a salesperson, each
 * as JSON. The path is not under a protected prefix, so it answers its own refusals.
 */
final readonly class SalesReportApi implements Api
{
    /**
     * @param ReportAccess     $reportAccess     Managers only.
     * @param RangeChecks      $rangeChecks      Reads the range.
     * @param FigureRepository $figureRepository The figures.
     */
    public function __construct(
        private ReportAccess $reportAccess,
        private RangeChecks $rangeChecks,
        private FigureRepository $figureRepository,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its query and session.
     *
     * @throws QueryException           When the figures cannot be read.
     * @throws LogicException           When the request carries no session, or an entity type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return ApiResponse The report; 401, 403 or 422 with why not.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        $staffMember = $this->reportAccess->member($serverRequest);

        if (!$staffMember instanceof StaffMember) {
            return new JsonResponse(new ApiMessage('Sign in to read the sales report.'), 401);
        }

        if (!$this->reportAccess->manages($staffMember)) {
            return new JsonResponse(new ApiMessage('Only a sales manager or an admin can read the sales report.'), 403);
        }

        $rangeCheck = $this->rangeChecks->query($serverRequest);

        if ($rangeCheck->refused()) {
            return new JsonResponse(new ApiMessage('That range cannot be reported on.', $rangeCheck->errors), 422);
        }

        $reportRange = $rangeCheck->reportRange;

        return new JsonResponse(new SalesReportJson($reportRange, $this->figureRepository->months($reportRange)));
    }
}
