<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Settings\Service\MemberMoves;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /app/settings/staff/{id}/link (#22): an admin mails a member a new password link, voiding their older ones; 303
 * to the member, 404 and 403.
 */
final readonly class MemberLinkPost implements Page
{
    /**
     * @param MemberMoves $memberMoves Changes a member's access.
     */
    public function __construct(
        private MemberMoves $memberMoves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted Send a password link form, with its session.
     *
     * @throws Throwable When the records cannot be read or written, or the page cannot be built.
     *
     * @return PageResponse 303 to the member; 404; 403 for anyone but an admin.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->memberMoves->link($serverRequest);
    }
}
