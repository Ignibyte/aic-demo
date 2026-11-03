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
 * PATCH /app/leads/{id:int}/stage: a card moved on the leads board (#30), by its menu or by drag. It answers the board,
 * which AIC answers with the two columns the action names; 422 for a stage that is none of the board's, 403 for
 * someone who may not move the lead, 404 for no lead.
 */
final readonly class LeadStagePatch implements Page
{
    /**
     * @param StageMoves $stageMoves The move.
     */
    public function __construct(
        private StageMoves $stageMoves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The move, with its `stage` and session.
     *
     * @throws Throwable When the lead cannot be read or written, the job cannot be queued, the request has no session,
     *                   or a key or a type does not fit; the move's transaction is rolled back first.
     *
     * @return PageResponse The board, 200; 422, 403 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->stageMoves->patch($serverRequest);
    }
}
