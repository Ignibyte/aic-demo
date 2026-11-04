<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageUnchanged;
use AIC\Database\Contracts\QueryException;
use App\Chat\Service\ChatMarks;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * POST /app/chats/{id}/typing (#32): the member is typing, so the visitor's stream shows it for five seconds. A chat
 * the member may not see, or one that has ended, takes nothing; it always answers 204.
 */
final readonly class ChatStaffTypingPost implements Page
{
    /**
     * @param ChatMarks $chatMarks Marks the member typing.
     */
    public function __construct(
        private ChatMarks $chatMarks,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The typing post, routed with the chat's `id`.
     *
     * @throws QueryException           When the chat cannot be read or written.
     * @throws LogicException           When the request carries no session, or a type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse 204.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $this->chatMarks->staffTyping($serverRequest);

        return new PageUnchanged();
    }
}
