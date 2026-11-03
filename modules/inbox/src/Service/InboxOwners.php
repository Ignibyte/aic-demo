<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use AIC\Auth\Application\Authenticator;
use AIC\Session\Application\RequestSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Whose inbox a request reads (#19): the signed-in member, as the user id `aic_notifications` keeps for them, their
 * `users.id` as text, which run 18's lead notices store. It is the id AIC's Authenticator keeps in the session under
 * `Authenticator::USER`, so no request reads the users table for it (#25: the profiler showed every staff page reading
 * the member twice, by id in `StaffStanding` and again by email here). `StaffStanding` has already signed out a member
 * no longer in `users`. It reads that key itself, as `Authenticator::userId()` does, rather than taking the
 * Authenticator: the generated wiring would then build one with its user provider and hasher in each of inbox's
 * handler parts, the same lines as staff's middleware, which gate:14 counts as copies.
 */
final readonly class InboxOwners
{
    /**
     * @param RequestSession $requestSession Reads the session off the request.
     */
    public function __construct(
        private RequestSession $requestSession = new RequestSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return string The signed-in member's user id; empty for a visitor.
     */
    public function userId(ServerRequestInterface $serverRequest): string
    {
        return $this->requestSession->read($serverRequest)->get(Authenticator::USER) ?? '';
    }
}
