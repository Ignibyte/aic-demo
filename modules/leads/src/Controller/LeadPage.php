<?php

declare(strict_types=1);

namespace App\Leads\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Leads\Entity\Lead;
use App\Leads\Service\LeadCards;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/leads/{id:int}: a lead and its car, with the reassign and close forms for its salesperson or a manager;
 * 404 when no lead has the id.
 */
final readonly class LeadPage implements Page
{
    /**
     * @param LeadCards $leadCards The lead and its pages.
     */
    public function __construct(
        private LeadCards $leadCards,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException           When the lead cannot be read.
     * @throws LogicException           When the request has no session, or a key, a type or a status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse The lead's page, or 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $lead = $this->leadCards->find($serverRequest);

        return $lead instanceof Lead
            ? $this->leadCards->page($serverRequest, $lead, '', 200)
            : $this->leadCards->missing();
    }
}
