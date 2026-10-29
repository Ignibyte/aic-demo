<?php

declare(strict_types=1);

namespace App\Inbox\Controller;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Inbox\Service\InboxList;
use App\Staff\Service\StaffPages;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/inbox: the signed-in member's notifications (#19), newest first, twenty a page, each linking to its
 * opening, with "Mark all read" while any is unread.
 */
final readonly class InboxPage implements Page
{
    /**
     * @param StaffPages $staffPages The staff pages' document.
     * @param InboxList  $inboxList  The inbox's panel.
     */
    public function __construct(
        private StaffPages $staffPages,
        private InboxList $inboxList,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff, with an optional `page`.
     *
     * @throws QueryException           When the member or their notifications cannot be read.
     * @throws LogicException           When the request carries no session, or a key, the users' type or the status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse The inbox.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->staffPages->page('Inbox', [new KeyedComponent('inbox', $this->inboxList->panel($serverRequest))], 200);
    }
}
