<?php

declare(strict_types=1);

namespace App\Staff\Middleware;

use AIC\Auth\Application\Authenticator;
use App\Staff\Entity\StaffMember;
use App\Staff\Repository\StaffRepository;
use App\Staff\Service\StaffSession;
use LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Holds a signed-in session to the users table on every request (#22), after the session and before the sign-in guard,
 * which the manifest lists after it. A member deleted or deactivated since they signed in is signed out here, so the
 * guard sends them to sign-in from anything under `/app`, and the rest of the site sees a visitor. So is a session
 * whose kept password stamp is not the table's (#23): their password was set anew since it signed in, through a link,
 * which ends every other session they had, or the session is from before the stamp was kept. Anyone else has the
 * name, email and role the table holds now written into their session, so a role an admin took away is gone on their
 * next page. A visitor's request reads nothing.
 */
final readonly class StaffStanding implements MiddlewareInterface
{
    /**
     * @param Authenticator   $authenticator   Knows the signed-in user's id, and signs them out.
     * @param StaffRepository $staffRepository Reads the member by that id.
     * @param StaffSession    $staffSession    The member the session keeps.
     */
    public function __construct(
        private Authenticator $authenticator,
        private StaffRepository $staffRepository,
        private StaffSession $staffSession = new StaffSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request, with its session.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws LogicException   When the request carries no session.
     * @throws RuntimeException When the users table cannot be read, or a stored role is no role.
     *
     * @return ResponseInterface The rest of the stack's response, for the member as the table has them now.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $this->authenticator->userId($request);

        if ($userId === '') {
            return $handler->handle($request);
        }

        $staffMember = $this->staffRepository->standing($userId);

        if (!$staffMember instanceof StaffMember || $this->staffSession->stamp($request) !== $staffMember->stamp) {
            $this->authenticator->logout($request);

            return $handler->handle($request);
        }

        $this->staffSession->refresh($request, $staffMember);

        return $handler->handle($request);
    }
}
