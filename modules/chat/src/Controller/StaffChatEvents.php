<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\EventStream;
use AIC\Database\Contracts\QueryException;
use App\Chat\Service\ChatStreams;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/chats/{id}/events (#32): the member's stream for one chat, as the visitor's is, marking what it sends seen.
 * A chat the member may not see, or one that is not there, sends one closing event. Signed out, the protected prefix
 * turns it away.
 */
final readonly class StaffChatEvents implements Stream
{
    /**
     * @param ChatStreams $chatStreams Builds the connection's source.
     */
    public function __construct(
        private ChatStreams $chatStreams,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the chat's `id`, from a signed-in member.
     *
     * @throws QueryException           When the chat cannot be read.
     * @throws LogicException           When the request carries no session, or a type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return StreamResponse The event stream.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return new EventStream($this->chatStreams->staff($serverRequest));
    }
}
