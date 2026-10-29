<?php

declare(strict_types=1);

namespace App\Inbox\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Inbox\Service\InboxMoves;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/inbox/{id:int}: opens one of the signed-in member's notifications (#19): marks it read and answers 303 to
 * what it is about, or to the inbox when it links nothing; 404 for a notification that is not theirs.
 */
final readonly class NotificationPage implements Page
{
    /**
     * @param InboxMoves $inboxMoves The opening.
     */
    public function __construct(
        private InboxMoves $inboxMoves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException           When the member or the notification cannot be read or written.
     * @throws LogicException           When the request carries no session, or the users' type or the status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse 303 on; 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->inboxMoves->open($serverRequest);
    }
}
