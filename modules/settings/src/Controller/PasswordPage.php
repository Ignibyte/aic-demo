<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Settings\Service\PasswordDesk;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * GET /password/{link} (#22): the page a mailed password link lands on, open to whoever holds it: the set-password form,
 * 400 for an altered token, 404 for a link used, voided or past its 24 hours.
 */
final readonly class PasswordPage implements Page
{
    /**
     * @param PasswordDesk $passwordDesk The set-password page and post.
     */
    public function __construct(
        private PasswordDesk $passwordDesk,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a link's page, with its session.
     *
     * @throws Throwable When the records cannot be read or written, or the page cannot be built.
     *
     * @return PageResponse The form; 400 for an altered link; 404 for one used or expired.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->passwordDesk->page($serverRequest);
    }
}
