<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use App\Reports\Repository\ExportRepository;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\StaffPanels;
use App\Staff\Service\StaffSession;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * Who may read the reports (#21): the managers, an admin or a sales manager, as leads has it; a salesperson is
 * refused with 403 in the staff page frame. Also the signed-in member's user id, found by their login, whose exports are
 * theirs; and the page an export post that names no report or range answers.
 */
final readonly class ReportAccess
{
    /**
     * Where a refusal's link goes.
     */
    private const string BACK = '/app/reports';

    /**
     * @param StaffPanels      $staffPanels      The staff pages' frame, as the panel `reports`.
     * @param ExportRepository $exportRepository Finds a member's id by their login.
     * @param StaffSession     $staffSession     Who is signed in.
     */
    public function __construct(
        private StaffPanels $staffPanels,
        private ExportRepository $exportRepository,
        private StaffSession $staffSession = new StaffSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws LogicException           When the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return StaffMember|null Who is signed in when they read the reports; null for anyone else.
     */
    public function manager(ServerRequestInterface $serverRequest): ?StaffMember
    {
        $staffMember = $this->member($serverRequest);

        return $this->manages($staffMember)
            ? $staffMember
            : null;
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws QueryException           When the users cannot be read.
     * @throws LogicException           When the request carries no session, or the users' type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return int The signed-in member's `users.id`; 0 for a visitor, or a member no longer in `users`.
     */
    public function userId(ServerRequestInterface $serverRequest): int
    {
        $staffMember = $this->member($serverRequest);

        return $staffMember instanceof StaffMember
            ? $this->exportRepository->memberId($staffMember->email)
            : 0;
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws LogicException           When the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return StaffMember|null Who is signed in; null for a visitor.
     */
    public function member(ServerRequestInterface $serverRequest): ?StaffMember
    {
        return $this->staffSession->member($serverRequest);
    }

    /**
     * @param StaffMember|null $staffMember Who is signed in, or null.
     *
     * @return bool Whether they read the reports: an admin or a sales manager.
     */
    public function manages(?StaffMember $staffMember): bool
    {
        return $staffMember instanceof StaffMember && $staffMember->role->manages();
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument 403: only a manager reads the reports.
     */
    public function refused(): PageDocument
    {
        $message = 'Only a sales manager or an admin can read the reports.';

        return $this->staffPanels->warning('Not allowed', $message, 'reports-refused', 'Back to the dashboard', '/app', 403);
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument 422: the post named no report the page has, or a range that is not one.
     */
    public function unexportable(): PageDocument
    {
        $message = 'Choose one of the reports and a range of days the calendar holds, then export it again.';

        return $this->staffPanels->warning('Nothing to export', $message, 'report-export-refused', 'Back to the reports', self::BACK, 422);
    }
}
