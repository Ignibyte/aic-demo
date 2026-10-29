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
 * POST /password/{link} (#22): sets the link's member's password and spends the link in one transaction, then 303 to
 * `/sign-in`; 422 for a password refused, 400 for an altered token, 404 for a link used, voided or past its 24 hours.
 */
final readonly class PasswordPost implements Page
{
    /**
     * @param PasswordDesk $passwordDesk The set-password page and post.
     */
    public function __construct(
        private PasswordDesk $passwordDesk,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted set-password form, with its session.
     *
     * @throws Throwable When the records cannot be read or written, or the page cannot be built.
     *
     * @return PageResponse 303 to sign-in; the form as 422; 400; 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->passwordDesk->post($serverRequest);
    }
}
