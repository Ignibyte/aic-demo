<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Auth\Application\Authenticator;
use AIC\Session\Application\RequestSession;
use App\Leads\Entity\BoardViewer;
use App\Leads\Entity\Lead;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\StaffSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function ctype_digit;
use function strlen;

/**
 * Who may reassign, close or move a lead (#18, #30): the salesperson it is assigned to, and a manager, an admin or a
 * sales manager. Everyone else on the staff reads it and nothing more. The rule itself is `BoardViewer`'s, so the
 * lead's page, the board, its stream and the stage routes ask the same one.
 */
final readonly class LeadAccess
{
    /**
     * @param StaffSession   $staffSession   Who is signed in.
     * @param RequestSession $requestSession Their user id, as AIC's sign-in keeps it.
     */
    public function __construct(
        private StaffSession $staffSession = new StaffSession(),
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A staff request, with its session.
     *
     * @throws LogicException           When the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return BoardViewer Whoever is signed in; no one (an empty email) when no member is.
     */
    public function viewer(ServerRequestInterface $serverRequest): BoardViewer
    {
        $staffMember = $this->staffSession->member($serverRequest);
        $userId = $this->requestSession->read($serverRequest)->get(Authenticator::USER) ?? '';
        $known = $userId !== '' && strlen($userId) <= 18 && ctype_digit($userId);

        return $staffMember instanceof StaffMember && $known
            ? new BoardViewer((int) $userId, $staffMember->email, $staffMember->name, $staffMember->role->manages())
            : new BoardViewer(0, '', '', false);
    }

    /**
     * @param ServerRequestInterface $serverRequest A staff request, with its session.
     * @param Lead                   $lead          The lead.
     *
     * @throws LogicException           When the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return bool Whether whoever is signed in may reassign, close or move it.
     */
    public function mayMove(ServerRequestInterface $serverRequest, Lead $lead): bool
    {
        return $this->viewer($serverRequest)->mayMove($lead);
    }
}
