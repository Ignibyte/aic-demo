<?php

declare(strict_types=1);

namespace App\Staff\Service;

use AIC\Session\Application\RequestSession;
use App\Staff\Entity\StaffMember;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * The signed-in member of staff, kept in the session at sign-in beside the Authenticator's user id, so the header's
 * block (which takes no services) and `/app` show them without the database. Signing out clears the whole session.
 */
final readonly class StaffSession
{
    /**
     * The session key that keeps their email.
     */
    private const string EMAIL = 'staff.email';

    /**
     * The session key that keeps their name.
     */
    private const string NAME = 'staff.name';

    /**
     * The session key that keeps their role's stored value.
     */
    private const string ROLE = 'staff.role';

    /**
     * @param RequestSession $requestSession Reads the session off the request.
     * @param Roles          $roles          Reads the stored role.
     */
    public function __construct(
        private RequestSession $requestSession = new RequestSession(),
        private Roles $roles = new Roles(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The sign-in post, with the session the Authenticator just signed in.
     * @param StaffMember            $staffMember   Who signed in.
     *
     * @throws LogicException When the request carries no session.
     */
    public function remember(ServerRequestInterface $serverRequest, StaffMember $staffMember): void
    {
        $session = $this->requestSession->read($serverRequest);
        $session->set(self::EMAIL, $staffMember->email);
        $session->set(self::NAME, $staffMember->name);
        $session->set(self::ROLE, $staffMember->role->value);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws LogicException           When the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return StaffMember|null Who is signed in; null for an anonymous visitor.
     */
    public function member(ServerRequestInterface $serverRequest): ?StaffMember
    {
        $session = $this->requestSession->read($serverRequest);
        $email = $session->get(self::EMAIL) ?? '';

        if ($email === '') {
            return null;
        }

        return new StaffMember($email, $session->get(self::NAME) ?? '', $this->roles->stored($session->get(self::ROLE) ?? ''));
    }
}
