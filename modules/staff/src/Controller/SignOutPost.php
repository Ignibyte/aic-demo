<?php

declare(strict_types=1);

namespace App\Staff\Controller;

use AIC\Auth\Application\Authenticator;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageFullVisit;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /sign-out: signs the member of staff out, ending the session, and goes home.
 */
final readonly class SignOutPost implements Page
{
    /**
     * @param Authenticator $authenticator Signs the member out.
     */
    public function __construct(
        private Authenticator $authenticator,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return PageResponse A full visit to /: a 303, or `Tessera-Full-Visit` for Tessera, so nothing of the session
     *                      survives in the page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $this->authenticator->logout($serverRequest);

        return new PageFullVisit('/');
    }
}
