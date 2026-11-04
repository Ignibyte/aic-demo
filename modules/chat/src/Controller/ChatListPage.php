<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Chat\Service\ChatInbox;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/chats (#32): the staff's chat inbox, the chats the signed-in member sees, open first; its poll answers 204
 * while nothing changed. Signed out, the protected prefix turns it away.
 */
final readonly class ChatListPage implements Page
{
    /**
     * @param ChatInbox $chatInbox The list.
     */
    public function __construct(
        private ChatInbox $chatInbox,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member, maybe the list's poll.
     *
     * @throws QueryException           When the chats cannot be read.
     * @throws LogicException           When the request carries no session, or the version cannot be sealed, or a type,
     *                                  a key or the status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse The list; 204 for its poll while nothing changed.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->chatInbox->list($serverRequest);
    }
}
