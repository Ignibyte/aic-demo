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
 * POST /app/settings/staff/{id}/active (#22): an admin deactivates a member (`active=0`) or reactivates them
 * (`active=1`); 303 to the member, 422 for the last active admin, 404 and 403.
 */
final readonly class MemberActivePost implements Page
{
    /**
     * @param MemberMoves $memberMoves Changes a member's access.
     */
    public function __construct(
        private MemberMoves $memberMoves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted Deactivate or Reactivate form, with its session.
     *
     * @throws Throwable When the records cannot be read or written, or the page cannot be built.
     *
     * @return PageResponse 303 to the member; their page as 422; 404; 403 for anyone but an admin.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->memberMoves->activate($serverRequest);
    }
}
