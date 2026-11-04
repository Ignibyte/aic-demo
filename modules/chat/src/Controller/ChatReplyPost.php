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
 * POST /app/chats/{id}/messages (#32): the member's reply; 303 to the chat from a plain form, the conversation's
 * fragments with the `chatSent` patch from Tessera; 422 refused, 409 for a chat that has ended, 404 or 403.
 */
final readonly class ChatReplyPost implements Page
{
    /**
     * @param ChatReplies $chatReplies The reply.
     */
    public function __construct(
        private ChatReplies $chatReplies,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted reply, routed with the chat's `id`.
     *
     * @throws Throwable When the chat or its messages cannot be read or written, or a key or a status does not fit.
     *
     * @return PageResponse The answer.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->chatReplies->reply($serverRequest);
    }
}
