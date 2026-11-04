<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Chat\Service\ChatReplies;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /app/chats/{id}/close (#32): the member ends the chat, which queues its transcript when the visitor left an
 * email; 303 to the list with a notice, 409 for a chat that has ended already, 404 or 403.
 */
final readonly class ChatClosePost implements Page
{
    /**
     * @param ChatReplies $chatReplies The close.
     */
    public function __construct(
        private ChatReplies $chatReplies,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted close, routed with the chat's `id`.
     *
     * @throws Throwable When the chat cannot be read or written, the job queued, or a key or a status does not fit.
     *
     * @return PageResponse The answer.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->chatReplies->close($serverRequest);
    }
}
