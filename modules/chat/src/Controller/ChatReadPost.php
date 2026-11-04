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

/**
 * POST /chat/read (#32): the visitor has read the chat, so its unread count goes to 0 at the stream's next look. The
 * dock posts it when it opens, and when a message lands while it is open. With no open chat it writes nothing; it
 * always answers 204.
 */
final readonly class ChatReadPost implements Page
{
    /**
     * @param ChatMarks $chatMarks Marks the chat read.
     */
    public function __construct(
        private ChatMarks $chatMarks,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The read post, with its session.
     *
     * @throws QueryException When the chat cannot be read or written.
     * @throws LogicException When the request carries no session, or a type is malformed.
     *
     * @return PageResponse 204.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $this->chatMarks->read($serverRequest);

        return new PageUnchanged();
    }
}
