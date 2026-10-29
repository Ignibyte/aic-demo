<?php

declare(strict_types=1);

namespace App\Leads\Service;

use App\Leads\Entity\Lead;
use App\Leads\Entity\Seller;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\StaffSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * Who may reassign or close a lead (#18): the salesperson it is assigned to, and a manager, an admin or a sales
 * manager. Everyone else on the staff reads it and nothing more.
 */
final readonly class LeadAccess
{
    /**
     * @param StaffSession $staffSession Who is signed in.
     */
    public function __construct(
        private StaffSession $staffSession = new StaffSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A staff request, with its session.
     * @param Lead                   $lead          The lead.
     *
     * @throws LogicException           When the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return bool Whether whoever is signed in may reassign or close it.
     */
    public function mayMove(ServerRequestInterface $serverRequest, Lead $lead): bool
    {
        $staffMember = $this->staffSession->member($serverRequest);

        if (!$staffMember instanceof StaffMember) {
            return false;
        }

        if ($staffMember->role->manages()) {
            return true;
        }

        $seller = $lead->seller;

        return $seller instanceof Seller && $seller->email === $staffMember->email;
    }
}
