<?php

declare(strict_types=1);

namespace App\Leads\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Leads\Service\LeadMoves;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * POST /app/leads/{id:int}/close: closes the lead (303 to the list); 409 when it is closed already, 403 for someone
 * who may not move it, 404 for no lead.
 */
final readonly class LeadClosePost implements Page
{
    /**
     * @param LeadMoves $leadMoves The reassign and close flows.
     */
    public function __construct(
        private LeadMoves $leadMoves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException           When the lead cannot be read or written.
     * @throws EntityNotFoundException  When the lead went meanwhile.
     * @throws LogicException           When the request has no session, or a key, a type or a status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse 303 to the list; 409, 403 or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->leadMoves->close($serverRequest);
    }
}
