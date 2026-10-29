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
 * GET /app/settings/staff/{id} (#22): a member's page for an admin: the form for their name and role, their access and
 * the buttons that change it; 404 for an id no member has, 403 for anyone but an admin.
 */
final readonly class MemberPage implements Page
{
    /**
     * @param MemberSaves $memberSaves Adds, shows and saves members.
     */
    public function __construct(
        private MemberSaves $memberSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a member's page, with its session.
     *
     * @throws Throwable When the records cannot be read or written, or the page cannot be built.
     *
     * @return PageResponse The page; 404; 403 for anyone but an admin.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->memberSaves->show($serverRequest);
    }
}
