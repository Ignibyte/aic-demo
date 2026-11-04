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
 * GET /app/chats/{id} (#32): a chat's conversation, live through its stream; opening it marks the chat seen. 404 for a
 * chat that is not there, 403 for one that went to another salesperson.
 */
final readonly class ChatViewPage implements Page
{
    /**
     * @param ChatInbox $chatInbox The conversation.
     */
    public function __construct(
        private ChatInbox $chatInbox,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the chat's `id`.
     *
     * @throws QueryException           When the chat or its messages cannot be read, or its seen mark written.
     * @throws LogicException           When the request carries no session, or a type, a key or the status does not fit.
     * @throws UnexpectedValueException When the kept role is no role, or a stored side is no side.
     *
     * @return PageResponse The conversation; 404 or 403.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->chatInbox->view($serverRequest);
    }
}
