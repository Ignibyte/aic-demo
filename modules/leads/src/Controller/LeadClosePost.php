<?php

declare(strict_types=1);

namespace App\Leads\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Leads\Form\StageMoves;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /app/leads/{id:int}/close: closes the lead, a move to Lost on the board (#30), 303 to the list; 409 when it is
 * closed already, 403 for someone who may not move it, 404 for no lead.
 */
final readonly class LeadClosePost implements Page
{
    /**
     * @param StageMoves $stageMoves The move, of which a close is one.
     */
    public function __construct(
        private StageMoves $stageMoves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws Throwable When the lead cannot be read or written, the request has no session, or a key, a type or a
     *                   status does not fit; the move's transaction is rolled back first.
     *
     * @return PageResponse 303 to the list; 409, 403 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->stageMoves->close($serverRequest);
    }
}
