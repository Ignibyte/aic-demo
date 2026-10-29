<?php

declare(strict_types=1);

namespace App\Leads\Controller;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Leads\Service\LeadList;
use App\Staff\Service\StaffPages;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/leads: the open leads, newest first, filtered by kind and by salesperson. The prefix is staff's, so a
 * visitor who is not signed in goes to sign in first.
 */
final readonly class LeadListPage implements Page
{
    /**
     * @param StaffPages $staffPages The staff pages' document.
     * @param LeadList   $leadList   The list.
     */
    public function __construct(
        private StaffPages $staffPages,
        private LeadList $leadList,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException When the leads cannot be read.
     * @throws LogicException When the request has no session, or a key, a type or a status does not fit.
     *
     * @return PageResponse The list.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->staffPages->page('Leads', [new KeyedComponent('leads', $this->leadList->panel($serverRequest))], 200);
    }
}
