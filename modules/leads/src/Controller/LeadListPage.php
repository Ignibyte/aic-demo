<?php

declare(strict_types=1);

namespace App\Leads\Controller;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Leads\Service\LeadBoard;
use App\Leads\Service\LeadList;
use App\Leads\Service\LeadQueries;
use App\Staff\Service\StaffPages;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/leads: the open leads, newest first, filtered by kind and by salesperson; with `view=board`, the leads board
 * (#30), every lead in its stage's column. The prefix is staff's, so a visitor who is not signed in goes to sign in first.
 */
final readonly class LeadListPage implements Page
{
    /**
     * @param StaffPages  $staffPages  The staff pages' document.
     * @param LeadList    $leadList    The list.
     * @param LeadBoard   $leadBoard   The board.
     * @param LeadQueries $leadQueries Reads which of the two is asked for, and gives the tabs between them.
     */
    public function __construct(
        private StaffPages $staffPages,
        private LeadList $leadList,
        private LeadBoard $leadBoard,
        private LeadQueries $leadQueries = new LeadQueries(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException           When the leads cannot be read.
     * @throws LogicException           When the request has no session, or a key, a type or a status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse The list, or the board.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        if ($this->leadQueries->board($serverRequest)) {
            return $this->leadBoard->page($serverRequest, 0, '', 200);
        }

        $panel = $this->leadList->panel($serverRequest, $this->leadQueries->views(false));

        return $this->staffPages->page('Leads', [new KeyedComponent('leads', $panel)], 200);
    }
}
