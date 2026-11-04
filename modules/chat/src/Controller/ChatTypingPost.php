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
 * POST /chat/typing (#32): the visitor is typing, so the member's stream shows it for five seconds. The send form posts
 * it at most every two seconds. With no open chat it writes nothing; it always answers 204.
 */
final readonly class ChatTypingPost implements Page
{
    /**
     * @param ChatMarks $chatMarks Marks the visitor typing.
     */
    public function __construct(
        private ChatMarks $chatMarks,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The typing post, with its session.
     *
     * @throws QueryException When the chat cannot be read or written.
     * @throws LogicException When the request carries no session, or a type is malformed.
     *
     * @return PageResponse 204.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $this->chatMarks->typing($serverRequest);

        return new PageUnchanged();
    }
}
