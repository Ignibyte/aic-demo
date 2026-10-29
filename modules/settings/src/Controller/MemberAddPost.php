<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Settings\Service\MemberSaves;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /app/settings/staff (#22): an admin adds a member from a name, an email and a role, with no password, and mails
 * them a link to set one; 303 to their page, 422 for a refused field or a taken email, 403 for anyone but an admin.
 */
final readonly class MemberAddPost implements Page
{
    /**
     * @param MemberSaves $memberSaves Adds, shows and saves members.
     */
    public function __construct(
        private MemberSaves $memberSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted add form, with its session.
     *
     * @throws Throwable When the records cannot be read or written, or the page cannot be built.
     *
     * @return PageResponse 303 to the new member; the settings page as 422; 403 for anyone but an admin.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->memberSaves->add($serverRequest);
    }
}
